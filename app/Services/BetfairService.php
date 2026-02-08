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
    private $ssoUrl;
    private $apiUrl = 'https://api.betfair.it/exchange/betting/json-rpc/v1';
    private $lastRequestTime = 0;

    public function __construct()
    {
        // Prioritize generic key, then Delayed Key as requested
        $this->appKey = Config::get('BETFAIR_APP_KEY') ?: (Config::get('BETFAIR_APP_KEY_DELAY') ?: Config::get('BETFAIR_APP_KEY_LIVE'));
        $this->username = Config::get('BETFAIR_USERNAME');
        $this->password = Config::get('BETFAIR_PASSWORD');
        $this->certPath = Config::get('BETFAIR_CERT_PATH');
        $this->keyPath = Config::get('BETFAIR_KEY_PATH');
        $this->ssoUrl = Config::get('BETFAIR_SSO_URL', 'https://identitysso.betfair.it/api/certlogin');

        // Permetti l'uso di un token manuale per bypassare l'autenticazione con certificati
        $this->sessionToken = Config::get('BETFAIR_SESSION_TOKEN');
    }

    public function isConfigured(): bool
    {
        // Configurato se abbiamo la chiave e (credenziali + certificati) OPPURE un session token manuale
        $hasKey = !empty($this->appKey);
        $hasCerts = !empty($this->username) && !empty($this->password) && !empty($this->certPath);
        $hasToken = !empty($this->sessionToken);

        return $hasKey && ($hasCerts || $hasToken);
    }

    private function authenticate()
    {
        if ($this->sessionToken)
            return $this->sessionToken;

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
        if (curl_errno($ch)) {
            error_log("Betfair Auth Error: " . curl_error($ch));
            return null;
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['sessionToken'])) {
            $this->sessionToken = $data['sessionToken'];
            return $this->sessionToken;
        }

        error_log("Betfair Auth Failed: " . $response);
        return null;
    }

    public function request(string $method, array $params = [])
    {
        $token = $this->authenticate();
        if (!$token)
            return null;

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

        return json_decode($response, true);
    }

    /**
     * Finds the Market ID and Runners for a given fixture and market name (e.g. 'MATCH_ODDS')
     */
    public function findMarket(string $matchName, string $marketType = 'MATCH_ODDS'): ?array
    {
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
        if (!$eventId)
            return null;

        // 2. List market catalogues for this event
        $markets = $this->request('listMarketCatalogue', [
            'filter' => [
                'eventIds' => [$eventId],
                'marketTypeCodes' => [$marketType]
            ],
            'marketProjection' => ['RUNNER_DESCRIPTION'],
            'maxResults' => 1
        ]);

        if (empty($markets['result']))
            return null;

        return [
            'marketId' => $markets['result'][0]['marketId'],
            'runners' => $markets['result'][0]['runners']
        ];
    }

    /**
     * Map AI advice to a selectionId
     */
    public function mapAdviceToSelection(string $advice, array $runners): ?string
    {
        $advice = strtolower($advice);

        // Handle common Match Odds advice
        if (strpos($advice, 'winner:') !== false) {
            $teamName = trim(explode(':', $advice)[1]);
            foreach ($runners as $r) {
                if (strpos(strtolower($r['runnerName']), strtolower($teamName)) !== false) {
                    return $r['selectionId'];
                }
            }
        }

        if (strpos($advice, 'draw') !== false) {
            foreach ($runners as $r) {
                if (strtolower($r['runnerName']) === 'the draw' || strtolower($r['runnerName']) === 'pareggio') {
                    return $r['selectionId'];
                }
            }
        }

        // Generic fallback: check if advice contains any runner name
        foreach ($runners as $r) {
            if (strpos($advice, strtolower($r['runnerName'])) !== false) {
                return $r['selectionId'];
            }
        }

        return null;
    }

    public function placeBet(string $marketId, string $selectionId, float $price, float $size): array
    {
        $token = $this->authenticate();
        if (!$token)
            return ['status' => 'FAILURE', 'errorCode' => 'NO_AUTH'];

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
        // Endpoint REST Betting per Italia: https://api.betfair.it/exchange/betting/rest/v1.0/placeOrders/
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.it/exchange/betting/rest/v1.0/placeOrders/');
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
            return ['status' => 'FAILURE', 'errorCode' => 'CURL_ERROR', 'raw' => $err];
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded ?: ['status' => 'FAILURE', 'errorCode' => 'API_ERROR_NO_JSON', 'raw' => $response];
    }

    /**
     * Recupera il saldo del conto Betfair (API Account)
     */
    public function getFunds()
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

        // REST Endpoint per Italia (JSON-RPC spesso non supportato o problematico su .it)
        // Usa: https://api.betfair.it/exchange/account/rest/v1.0/getAccountFunds/

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.betfair.it/exchange/account/rest/v1.0/getAccountFunds/');
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
            return ['error' => 'CURL_ERROR', 'details' => $err];
        }
        curl_close($ch);

        return json_decode($response, true);
    }
}
