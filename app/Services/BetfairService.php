<?php
// app/Services/BetfairService.php

namespace App\Services;

use App\Config\Config;

class BetfairService
{
    private $appKey;
    private $username;
    private $password;
    private $certPath;
    private $keyPath;
    private $sessionToken;
    private $sessionFile;
    private $loginAttemptFile;
    private $activityFile;
    private $lockFile;
    private $authFailed = false;
    private $ssoUrl;
    private $keepAliveUrl;
    private $apiUrl = 'https://api.betfair.com/exchange/betting/json-rpc/v1';
    private $lastRequestTime = 0;
    private $logFile;

    public function __construct()
    {
        // Prioritize generic key, then Delayed Key as requested
        $this->appKey = Config::get('BETFAIR_APP_KEY') ?: (Config::get('BETFAIR_APP_KEY_DELAY') ?: Config::get('BETFAIR_APP_KEY_LIVE'));
        $this->username = Config::get('BETFAIR_USERNAME');
        $this->password = Config::get('BETFAIR_PASSWORD');
        $this->certPath = Config::get('BETFAIR_CERT_PATH');
        $this->keyPath = Config::get('BETFAIR_KEY_PATH');
        $this->ssoUrl = Config::get('BETFAIR_SSO_URL', 'https://identitysso.betfair.it/api/certlogin');
        $this->keepAliveUrl = 'https://identitysso.betfair.it/api/keepAlive';

        $this->sessionFile = Config::DATA_PATH . 'betfair_session.txt';
        $this->loginAttemptFile = Config::DATA_PATH . 'last_login_attempt.txt';
        $this->activityFile = Config::DATA_PATH . 'betfair_last_activity.txt';
        $this->lockFile = Config::DATA_PATH . 'betfair_login.lock';

        // Carica token persistente o da configurazione
        $this->sessionToken = $this->loadPersistentToken() ?: Config::get('BETFAIR_SESSION_TOKEN');

        $this->logFile = __DIR__ . '/../../logs/betfair_debug.log';
    }

    private function loadPersistentToken()
    {
        if (file_exists($this->sessionFile)) {
            $token = trim(file_get_contents($this->sessionFile));
            return !empty($token) ? $token : null;
        }
        return null;
    }

    private function savePersistentToken($token)
    {
        if (!is_dir(dirname($this->sessionFile))) {
            mkdir(dirname($this->sessionFile), 0777, true);
        }
        file_put_contents($this->sessionFile, $token);
        $this->updateLastActivity();
    }

    private function clearPersistentToken($reason = "unspecified")
    {
        $this->log("Invalido il token persistente. Ragione: $reason");
        if (file_exists($this->sessionFile)) {
            unlink($this->sessionFile);
        }
        if (file_exists($this->activityFile)) {
            unlink($this->activityFile);
        }
        $this->sessionToken = null;
    }

    private function updateLastActivity()
    {
        if (!is_dir(dirname($this->activityFile))) {
            mkdir(dirname($this->activityFile), 0777, true);
        }
        file_put_contents($this->activityFile, time());
    }

    private function log($message, $data = null)
    {
        // Disabilitato come da richiesta: il file betfair_debug.log era troppo grande
        return;

        /*
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message";
        if ($data !== null) {
            $logEntry .= " | Data: " . json_encode($data);
        }
        file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND);
        */
    }

    public function isConfigured(): bool
    {
        // Configurato se abbiamo la chiave e le credenziali (username/password)
        // I certificati sono opzionali per il login API Desktop
        $hasKey = !empty($this->appKey);
        $hasCredentials = !empty($this->username) && !empty($this->password);
        $hasToken = !empty($this->sessionToken);

        return $hasKey && ($hasCredentials || $hasToken);
    }

    public function keepAlive()
    {
        if (!$this->sessionToken)
            return false;

        $this->log("Inizio Keep Alive per estendere la sessione...");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->keepAliveUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$this->sessionToken}",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (isset($data['status']) && $data['status'] === 'SUCCESS') {
            $this->log("Keep Alive RIUSCITO.");
            if (isset($data['token']) && $data['token'] !== $this->sessionToken) {
                $this->sessionToken = $data['token'];
                $this->savePersistentToken($this->sessionToken);
            } else {
                $this->updateLastActivity();
            }
            return true;
        }

        $this->log("Keep Alive FALLITO.", $data);
        if (isset($data['error']) && in_array($data['error'], ['INVALID_SESSION_INFORMATION', 'NO_SESSION'])) {
            $this->clearPersistentToken("Keep Alive Error: " . $data['error']);
        }
        return false;
    }

    public function authenticate($force = false)
    {
        // 1. Verifica token in memoria e necessità di Keep Alive
        if ($this->sessionToken && !$force) {
            $lastActivity = file_exists($this->activityFile) ? (int) file_get_contents($this->activityFile) : 0;
            if (time() - $lastActivity > 14400) { // 4 ore
                $this->keepAlive();
            }
            return $this->sessionToken;
        }

        if ($this->authFailed && !$force) {
            return null;
        }

        // 2. Controllo Ban Temporaneo (20m)
        $banFile = sys_get_temp_dir() . '/betfair_login_lock';
        if (file_exists($banFile)) {
            $lockTime = filemtime($banFile);
            if (time() - $lockTime < 1200) { // 20 minuti
                $this->log("Login BLOCCATO preventivamente per ban temporaneo attivo.");
                return null;
            }
            unlink($banFile);
        }

        // 3. LOGIN ATOMICO con flock
        if (!is_dir(dirname($this->lockFile))) {
            mkdir(dirname($this->lockFile), 0777, true);
        }
        $fp = fopen($this->lockFile, "w+");
        if (!$fp) {
            $this->log("Impossibile aprire il file di lock per il login.");
            return $this->sessionToken; // Riprova con quello che abbiamo
        }

        if (flock($fp, LOCK_EX)) {
            // DOUBLE CHECK: Ricarica il token dal file, forse un altro processo ha appena loggato
            $this->sessionToken = $this->loadPersistentToken();
            if ($this->sessionToken && !$force) {
                $this->log("Login saltato: un altro processo ha già effettuato l'autenticazione.");
                flock($fp, LOCK_UN);
                fclose($fp);
                return $this->sessionToken;
            }

            // Necessario il login - Controllo Cooldown
            if (file_exists($this->loginAttemptFile)) {
                $lastAttempt = (int) file_get_contents($this->loginAttemptFile);
                if ((time() - $lastAttempt) < 60 && !$force) {
                    $this->log("Autenticazione in cooldown (global). Salto.");
                    $this->authFailed = true;
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    return null;
                }
            }

            // Registra il tentativo
            file_put_contents($this->loginAttemptFile, time());
            $this->log("Inizio procedura di autenticazione (LOCK ACQUISITO)...");

            // --- TENTATIVO CON CERTIFICATI ---
            if (!empty($this->certPath) && file_exists($this->certPath)) {
                $this->log("Tentativo di login con certificati...");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->ssoUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "username={$this->username}&password={$this->password}");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "X-Application: {$this->appKey}",
                    "Content-Type: application/x-www-form-urlencoded"
                ]);
                curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
                curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);

                $response = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($response, true);

                if (isset($data['sessionToken'])) {
                    $this->sessionToken = $data['sessionToken'];
                    $this->savePersistentToken($this->sessionToken);
                    $this->log("Login con certificati riuscito.");
                    if (file_exists($this->loginAttemptFile))
                        unlink($this->loginAttemptFile);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    return $this->sessionToken;
                }
                $this->log("Login con certificati fallito.", $data);
            }

            // --- TENTATIVO SENZA CERTIFICATI (API DESKTOP) ---
            $this->log("Tentativo di login senza certificati (API Desktop)...");
            $loginUrl = 'https://identitysso.betfair.it/api/login';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $loginUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $this->username, 'password' => $this->password]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "X-Application: {$this->appKey}",
                "Content-Type: application/x-www-form-urlencoded",
                "Accept: application/json"
            ]);

            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);

            if (isset($data['token']) && !empty($data['token'])) {
                $this->sessionToken = $data['token'];
                $this->savePersistentToken($this->sessionToken);
                $this->log("Login senza certificati riuscito.");
                if (file_exists($this->loginAttemptFile))
                    unlink($this->loginAttemptFile);
                flock($fp, LOCK_UN);
                fclose($fp);
                return $this->sessionToken;
            }

            // Gestione Errori Critici
            if (isset($data['error'])) {
                $criticalErrors = ['TEMPORARY_BAN_TOO_MANY_REQUESTS', 'ACCOUNT_PENDING_PASSWORD_CHANGE', 'ACCOUNT_LOCKED'];
                if (in_array($data['error'], $criticalErrors)) {
                    $this->log("ERRORE CRITICO LOGIN: " . $data['error'] . ". Attivo ban temporaneo di 20 minuti.");
                    touch($banFile);
                }

                if ($data['error'] === 'STRONG_AUTH_CODE_REQUIRED') {
                    $this->log("ERRORE CRITICO: Autenticazione a 2 fattori (2FA) rilevata. L'API Desktop non può accedere senza codice. Soluzioni: 1. Configura i certificati SSL nel .env; 2. Disabilita temporaneamente la 2FA su Betfair.it; 3. Inserisci un token manualmente in " . $this->sessionFile);
                }
            }

            $this->log("Login fallito.", $data);
            $this->authFailed = true;
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return null;
    }

    public function request(string $method, array $params = [], $isRetry = false, string $prefix = 'SportsAPING/v1.0/', ?string $url = null)
    {
        $token = $this->authenticate();
        if (!$token) {
            $this->log("Request Failed: No Auth Token for method $method");
            return null;
        }

        // Rate Limiting: max 5 requests per second (0.2s interval)
        $now = microtime(true);
        $elapsed = $now - $this->lastRequestTime;
        if ($elapsed < 0.2) {
            usleep((0.2 - $elapsed) * 1000000);
        }
        $this->lastRequestTime = microtime(true);

        $payload = json_encode([
            "jsonrpc" => "2.0",
            "method" => $prefix . $method,
            "params" => $params,
            "id" => 1
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url ?? $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        // Controllo sessione scaduta
        if (isset($decoded['error']['data']['exceptionname']) && $decoded['error']['data']['exceptionname'] === 'APINGException') {
            $errorCode = $decoded['error']['data']['APINGException']['errorCode'] ?? '';
            if ($errorCode === 'INVALID_SESSION_INFORMATION' && !$isRetry) {
                $this->log("Sessione scaduta (JSON-RPC: $method). Tento il refresh...");
                $this->clearPersistentToken("INVALID_SESSION_INFORMATION (JSON-RPC)");
                return $this->request($method, $params, true);
            }
        }

        $this->log("JSON-RPC Request: $method", ['params' => $params, 'response' => $decoded]);

        return $decoded;
    }

    /**
     * Finds the Market ID and Runners for a given fixture and advice (determines market type)
     */
    public function findMarket(string $matchName, string $advice = ''): ?array
    {
        $marketType = 'MATCH_ODDS';
        $advice = strtolower($advice);
        if (strpos($advice, 'over') !== false || strpos($advice, 'under') !== false) {
            $marketType = 'OVER_UNDER_25';
        } elseif (strpos($advice, 'both teams to score') !== false || strpos($advice, 'bts') !== false || strpos($advice, 'gol/nogol') !== false) {
            $marketType = 'BOTH_TEAMS_TO_SCORE';
        }

        $this->log("Finding market for: $matchName (Detected Type: $marketType for advice: $advice)");

        // 1. List events by name
        $events = $this->request('listEvents', [
            'filter' => [
                'textQuery' => $matchName,
                'eventTypeIds' => ["1"] // Football
            ]
        ]);

        if (empty($events['result'])) {
            // Try with parts of the name
            $parts = explode(' vs ', strtolower($matchName));
            if (count($parts) === 2) {
                $events = $this->request('listEvents', [
                    'filter' => [
                        'textQuery' => $parts[0],
                        'eventTypeIds' => ["1"]
                    ]
                ]);
            }
        }

        $eventId = $events['result'][0]['event']['id'] ?? null;
        if (!$eventId) {
            $this->log("Event not found for $matchName");
            return null;
        }

        $this->log("Event found: " . $events['result'][0]['event']['name'] . " (ID: $eventId)");

        // 2. List market catalogues for this event
        $markets = $this->request('listMarketCatalogue', [
            'filter' => [
                'eventIds' => [$eventId],
                'marketTypeCodes' => [$marketType]
            ],
            'marketProjection' => ['RUNNER_DESCRIPTION'],
            'maxResults' => 1
        ]);

        if (empty($markets['result'])) {
            $this->log("Market $marketType not found for event ID $eventId");
            return null;
        }

        $this->log("Market found: " . $markets['result'][0]['marketId']);

        return [
            'marketId' => $markets['result'][0]['marketId'],
            'runners' => $markets['result'][0]['runners']
        ];
    }

    /**
     * Map AI advice to a selectionId
     */
    public function mapAdviceToSelection(string $advice, array $runners, string $homeTeam = '', string $awayTeam = ''): ?string
    {
        $this->log("Mapping advice to selection: $advice", ['runners' => $runners, 'home' => $homeTeam, 'away' => $awayTeam]);
        $advice = trim(strtolower($advice));

        // 1. Exact Name Matching (Best for multi-sport)
        foreach ($runners as $r) {
            $name = trim(strtolower($r['runnerName']));
            if ($advice === $name)
                return (string) $r['selectionId'];
        }

        // 2. Handle Over/Under and BTS
        if (strpos($advice, 'over 2.5') !== false) {
            foreach ($runners as $r) {
                if (stripos($r['runnerName'], 'over') !== false)
                    return (string) $r['selectionId'];
            }
        }
        if (strpos($advice, 'under 2.5') !== false) {
            foreach ($runners as $r) {
                if (stripos($r['runnerName'], 'under') !== false)
                    return (string) $r['selectionId'];
            }
        }
        if (strpos($advice, 'bts') !== false || strpos($advice, 'both teams to score') !== false || strpos($advice, 'yes') !== false) {
            foreach ($runners as $r) {
                if (stripos($r['runnerName'], 'yes') !== false)
                    return (string) $r['selectionId'];
            }
        }

        // 3. Handle explicit 1, X, 2 or Home, Away, Draw
        if ($advice === '1' || $advice === 'home' || $advice === 'casa' || ($homeTeam && strpos($advice, strtolower($homeTeam)) !== false)) {
            if ($homeTeam) {
                foreach ($runners as $r) {
                    if (stripos($r['runnerName'], $homeTeam) !== false)
                        return (string) $r['selectionId'];
                }
            }
            return (string) ($runners[0]['selectionId'] ?? null);
        }

        if ($advice === '2' || $advice === 'away' || $advice === 'trasferta' || ($awayTeam && strpos($advice, strtolower($awayTeam)) !== false)) {
            if ($awayTeam) {
                foreach ($runners as $r) {
                    if (stripos($r['runnerName'], $awayTeam) !== false)
                        return (string) $r['selectionId'];
                }
            }
            return (string) ($runners[1]['selectionId'] ?? null);
        }

        // 4. Fuzzy Fallback
        foreach ($runners as $r) {
            $name = strtolower($r['runnerName']);
            if (strpos($advice, $name) !== false || strpos($name, $advice) !== false) {
                return (string) $r['selectionId'];
            }
        }

        return null;
    }

    public function placeBet(string $marketId, string $selectionId, float $price, float $size, $isRetry = false): array
    {
        // Regole Betfair.it: Minimo 2€, multipli di 0.50€
        if ($size < \App\Config\Config::MIN_BETFAIR_STAKE) {
            $this->log("Stake $size aumentato a 2.00€ (minimo IT)");
            $size = \App\Config\Config::MIN_BETFAIR_STAKE;
        }

        $roundedStake = round($size * 2) / 2;
        if ($roundedStake < \App\Config\Config::MIN_BETFAIR_STAKE)
            $roundedStake = \App\Config\Config::MIN_BETFAIR_STAKE;
        if ($roundedStake != $size) {
            $this->log("Stake $size arrotondato a $roundedStake (multipli 0.50 IT)");
            $size = $roundedStake;
        }

        $this->log("Placing bet: Market=$marketId, Selection=$selectionId, Price=$price, Size=$size");
        $token = $this->authenticate();
        if (!$token) {
            $this->log("PlaceBet Failed: No Auth Token");
            return ['status' => 'FAILURE', 'errorCode' => 'NO_AUTH'];
        }

        $marketId = (string) $marketId;

        // Costruisce il payload REST per placeOrders (no JSON-RPC wrapper)
        $params = [
            'marketId' => $marketId,
            'instructions' => [
                [
                    'selectionId' => (string) $selectionId,
                    'handicap' => '0',
                    'side' => 'BACK',
                    'orderType' => 'LIMIT',
                    'limitOrder' => [
                        'size' => number_format((float) $size, 2, '.', ''),
                        'price' => number_format((float) $price, 2, '.', ''),
                        'persistenceType' => 'LAPSE'
                    ]
                ]
            ]
        ];

        // Rate Limit (semplificato)
        $now = microtime(true);
        if (($now - $this->lastRequestTime) < 0.2)
            usleep(200000);
        $this->lastRequestTime = microtime(true);

        $ch = curl_init();
        // Endpoint REST Betting (comune per IT se autenticati su .it): https://api.betfair.com/exchange/betting/rest/v1.0/placeOrders/
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/betting/rest/v1.0/placeOrders/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        // Fix SSL locale
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->log("PlaceBet CURL Error: $err");
            return ['status' => 'FAILURE', 'errorCode' => 'CURL_ERROR', 'raw' => $err];
        }
        curl_close($ch);

        $decoded = json_decode($response, true);

        // Controllo sessione scaduta per REST
        if (isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION' && !$isRetry) {
            $this->log("Sessione scaduta (REST Betting: placeBet). Tento il refresh...");
            $this->clearPersistentToken("INVALID_SESSION_INFORMATION (REST Betting)");
            return $this->placeBet($marketId, $selectionId, $price, $size, true);
        }

        $this->log("PlaceBet Response", $decoded);

        return $decoded ?: ['status' => 'FAILURE', 'errorCode' => 'API_ERROR_NO_JSON', 'raw' => $response];
    }

    /**
     * Recupera il saldo del conto Betfair (API Account)
     */
    public function getFunds($isRetry = false)
    {
        $token = $this->authenticate();
        if (!$token)
            return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/account/rest/v1.0/getAccountFunds/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        $isExpired = isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION';
        if (isset($decoded['detail']['AccountAPINGException']['errorCode']) && $decoded['detail']['AccountAPINGException']['errorCode'] === 'INVALID_SESSION_INFORMATION')
            $isExpired = true;

        if ($isExpired && !$isRetry) {
            $this->clearPersistentToken("INVALID_SESSION_INFORMATION (getFunds)");
            return $this->getFunds(true);
        }

        $this->log("getAccountFunds response: ", $decoded);
        return $decoded;
    }

    /**
     * Multi-Sport Discovery: List all sport types
     */
    public function getEventTypes()
    {
        return $this->request('listEventTypes', ['filter' => new \stdClass()]);
    }

    /**
     * Fetch all live events for specific sport IDs
     */
    public function getLiveEvents(array $eventTypeIds = ["1"])
    {
        return $this->request('listEvents', [
            'filter' => [
                'eventTypeIds' => $eventTypeIds,
                'inPlayOnly' => true
            ]
        ]);
    }

    /**
     * Fetch upcoming events for specific sport IDs within the next X hours
     */
    public function getUpcomingEvents(array $eventTypeIds = ["1"], int $hours = 24)
    {
        $from = gmdate('Y-m-d\TH:i:s\Z', time() - (2 * 3600)); // Include recently started
        $to = gmdate('Y-m-d\TH:i:s\Z', time() + ($hours * 3600));

        return $this->request('listEvents', [
            'filter' => [
                'eventTypeIds' => $eventTypeIds,
                'marketStartTime' => [
                    'from' => $from,
                    'to' => $to
                ]
            ]
        ]);
    }

    /**
     * Get Market Catalogues for a list of Event IDs with filtering and sorting
     */
    public function getMarketCatalogues(array $eventIds, int $maxResults = 50, array $marketTypeCodes = [], string $sort = 'MAXIMUM_TRADED')
    {
        $filter = [
            'eventIds' => $eventIds,
            'marketBettingTypes' => ['ODDS']
        ];

        if (!empty($marketTypeCodes)) {
            $filter['marketTypeCodes'] = $marketTypeCodes;
        }

        return $this->request('listMarketCatalogue', [
            'filter' => $filter,
            'maxResults' => $maxResults,
            'sort' => $sort,
            'marketProjection' => ['RUNNER_DESCRIPTION', 'MARKET_DESCRIPTION', 'EVENT', 'COMPETITION', 'EVENT_TYPE']
        ]);
    }

    /**
     * Get Prices for specific Market IDs
     */
    public function getMarketBooks(array $marketIds)
    {
        return $this->request('listMarketBook', [
            'marketIds' => $marketIds,
            'priceProjection' => [
                'priceData' => ['EX_BEST_OFFERS'],
                'virtualise' => true
            ],
            'includeMarketDefinition' => true
        ]);
    }

    /**
     * Get Account Statement for tracker
     */
    public function getAccountStatement($isRetry = false)
    {
        $token = $this->authenticate();
        if (!$token)
            return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/account/rest/v1.0/getAccountStatement/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['itemClass' => 'TRANSACTION']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION' && !$isRetry) {
            $this->clearPersistentToken("INVALID_SESSION_INFORMATION (getAccountStatement)");
            return $this->getAccountStatement(true);
        }

        return $decoded;
    }

    /**
     * Get Settled Bets (Cleared Orders)
     */
    public function getClearedOrders($isRetry = false)
    {
        $token = $this->authenticate();
        if (!$token)
            return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/betting/rest/v1.0/listClearedOrders/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'betStatus' => 'SETTLED',
            'recordCount' => 1000,
            'includeItemDescription' => true
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION' && !$isRetry) {
            $this->clearPersistentToken("INVALID_SESSION_INFORMATION (getClearedOrders)");
            return $this->getClearedOrders(true);
        }

        return $decoded;
    }

    /**
     * Get Open Bets
     */
    public function getCurrentOrders($isRetry = false)
    {
        $token = $this->authenticate();
        if (!$token)
            return null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/betting/rest/v1.0/listCurrentOrders/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION' && !$isRetry) {
            $this->clearPersistentToken("INVALID_SESSION_INFORMATION (getCurrentOrders)");
            return $this->getCurrentOrders(true);
        }

        return $decoded;
    }
}
