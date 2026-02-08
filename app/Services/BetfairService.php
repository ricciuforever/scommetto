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
    private $ssoUrl;
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
        $this->sessionFile = Config::DATA_PATH . 'betfair_session.txt';

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
    }

    private function clearPersistentToken()
    {
        if (file_exists($this->sessionFile)) {
            unlink($this->sessionFile);
        }
        $this->sessionToken = null;
    }

    private function log($message, $data = null)
    {
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

    private function authenticate($force = false)
    {
        if ($this->sessionToken && !$force) {
            return $this->sessionToken;
        }

        $this->log("Inizio procedura di autenticazione...");

        // Se abbiamo i certificati, usiamo ssoUrl (certlogin)
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
                return $this->sessionToken;
            }
            $this->log("Login con certificati fallito.", $data);
        }

        // Altrimenti proviamo il login interattivo/API Desktop (senza certificati)
        $this->log("Tentativo di login senza certificati (API Desktop)...");
        $loginUrl = 'https://identitysso.betfair.it/api/login';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $this->username,
            'password' => $this->password
        ]));
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
            return $this->sessionToken;
        }

        if (isset($data['error']) && $data['error'] === 'STRONG_AUTH_CODE_REQUIRED') {
            $this->log("ERRORE CRITICO: Autenticazione a 2 fattori (2FA) rilevata. L'API Desktop non può accedere senza codice. Soluzioni: 1. Configura i certificati SSL nel .env; 2. Disabilita temporaneamente la 2FA su Betfair.it; 3. Inserisci un token manualmente in " . $this->sessionFile);
        }

        $this->log("Login senza certificati fallito.", $data);
        return null;
    }

    public function request(string $method, array $params = [], $isRetry = false)
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
            "method" => "SportsAPING/v1.0/" . $method,
            "params" => $params,
            "id" => 1
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
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
                 $this->log("Sessione scaduta (JSON-RPC). Tento il refresh...");
                 $this->clearPersistentToken();
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

        // 0. Handle Over/Under and BTS
        if (strpos($advice, 'over 2.5') !== false) {
            foreach ($runners as $r) { if (stripos($r['runnerName'], 'over') !== false) return $r['selectionId']; }
        }
        if (strpos($advice, 'under 2.5') !== false) {
            foreach ($runners as $r) { if (stripos($r['runnerName'], 'under') !== false) return $r['selectionId']; }
        }
        if (strpos($advice, 'bts') !== false || strpos($advice, 'both teams to score') !== false || strpos($advice, 'yes') !== false) {
             foreach ($runners as $r) { if (stripos($r['runnerName'], 'yes') !== false) return $r['selectionId']; }
        }
        if (strpos($advice, 'no bts') !== false || strpos($advice, 'no') !== false) {
             foreach ($runners as $r) { if (stripos($r['runnerName'], 'no') !== false) return $r['selectionId']; }
        }

        // 1. Handle explicit 1, X, 2 or Home, Away, Draw
        if ($advice === '1' || $advice === 'home' || $advice === 'casa') {
            if ($homeTeam) {
                foreach ($runners as $r) {
                    if (strpos(strtolower($r['runnerName']), strtolower($homeTeam)) !== false) return $r['selectionId'];
                }
            }
            // Fallback: usually the first runner is Home
            return $runners[0]['selectionId'] ?? null;
        }

        if ($advice === '2' || $advice === 'away' || $advice === 'trasferta') {
            if ($awayTeam) {
                foreach ($runners as $r) {
                    if (strpos(strtolower($r['runnerName']), strtolower($awayTeam)) !== false) return $r['selectionId'];
                }
            }
            // Fallback: usually the second runner is Away
            return $runners[1]['selectionId'] ?? null;
        }

        if ($advice === 'x' || $advice === 'draw' || $advice === 'pareggio' || $advice === 'the draw') {
            foreach ($runners as $r) {
                $name = strtolower($r['runnerName']);
                if ($name === 'the draw' || $name === 'pareggio' || $name === 'draw' || $name === 'x') {
                    return $r['selectionId'];
                }
            }
            // Fallback: usually the third runner is Draw
            return $runners[2]['selectionId'] ?? null;
        }

        // 2. Handle common Match Odds advice with prefixes
        if (strpos($advice, 'winner:') !== false || strpos($advice, 'vincente:') !== false) {
            $teamName = trim(explode(':', $advice)[1]);
            foreach ($runners as $r) {
                if (strpos(strtolower($r['runnerName']), strtolower($teamName)) !== false) {
                    return $r['selectionId'];
                }
            }
        }

        // 3. Generic fallback: check if advice contains any runner name
        foreach ($runners as $r) {
            if (strpos($advice, strtolower($r['runnerName'])) !== false) {
                return $r['selectionId'];
            }
        }

        // 4. If advice is a team name directly
        if ($homeTeam && strpos($advice, strtolower($homeTeam)) !== false) {
             foreach ($runners as $r) {
                if (strpos(strtolower($r['runnerName']), strtolower($homeTeam)) !== false) return $r['selectionId'];
            }
        }
        if ($awayTeam && strpos($advice, strtolower($awayTeam)) !== false) {
             foreach ($runners as $r) {
                if (strpos(strtolower($r['runnerName']), strtolower($awayTeam)) !== false) return $r['selectionId'];
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

        $roundedStake = floor($size * 2) / 2;
        if ($roundedStake < \App\Config\Config::MIN_BETFAIR_STAKE) $roundedStake = \App\Config\Config::MIN_BETFAIR_STAKE;
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
            $this->log("Sessione scaduta (REST Betting). Tento il refresh...");
            $this->clearPersistentToken();
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
        // Nota: Account API è su un endpoint diverso, ma molte librerie usano lo stesso URL base per semplicità.
        // Se necessario, cambiare URL base. Per Betfair Exchange API standard 'SportsAPING' e 'AccountAPING' sono separati.
        // URL Account: https://api.betfair.com/exchange/account/json-rpc/v1

        $token = $this->authenticate();
        if (!$token)
            return null;

        // Rate Limit check (semplificato)
        $now = microtime(true);
        if (($now - $this->lastRequestTime) < 0.2)
            usleep(200000);
        $this->lastRequestTime = microtime(true);

        // REST Endpoint Account (comune per IT se autenticati su .it)
        // Usa: https://api.betfair.com/exchange/account/rest/v1.0/getAccountFunds/

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.com/exchange/account/rest/v1.0/getAccountFunds/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // Body vuoto o filtro vuoto
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
            $this->log("GetFunds CURL Error: $err");
            return ['error' => 'CURL_ERROR', 'details' => $err];
        }
        curl_close($ch);

        $decoded = json_decode($response, true);

        // Controllo sessione scaduta per REST Account (gestisce sia top-level errorCode che nested fault structure)
        $isExpired = false;
        if (isset($decoded['errorCode']) && $decoded['errorCode'] === 'INVALID_SESSION_INFORMATION') {
            $isExpired = true;
        } elseif (isset($decoded['detail']['AccountAPINGException']['errorCode']) && $decoded['detail']['AccountAPINGException']['errorCode'] === 'INVALID_SESSION_INFORMATION') {
            $isExpired = true;
        }

        if ($isExpired && !$isRetry) {
            $this->log("Sessione scaduta (Account REST). Tento il refresh...");
            $this->clearPersistentToken();
            return $this->getFunds(true);
        }

        $this->log("GetFunds Response", $decoded);
        return $decoded;
    }
}
