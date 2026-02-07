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
    private $apiUrl = 'https://api.betfair.com/exchange/betting/json-rpc/v1';

    public function __construct()
    {
        $this->appKey = Config::get('BETFAIR_APP_KEY_LIVE');
        $this->username = Config::get('BETFAIR_USERNAME');
        $this->password = Config::get('BETFAIR_PASSWORD');
        $this->certPath = Config::get('BETFAIR_CERT_PATH');
        $this->keyPath = Config::get('BETFAIR_KEY_PATH');
        $this->ssoUrl = Config::get('BETFAIR_SSO_URL', 'https://identitysso.betfair.it/api/certlogin');
    }

    public function isConfigured(): bool
    {
        return !empty($this->appKey) && !empty($this->username) && !empty($this->password) && !empty($this->certPath);
    }

    private function authenticate()
    {
        if ($this->sessionToken) return $this->sessionToken;

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
        if (!$token) return null;

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
        if (!$eventId) return null;

        // 2. List market catalogues for this event
        $markets = $this->request('listMarketCatalogue', [
            'filter' => [
                'eventIds' => [$eventId],
                'marketTypeCodes' => [$marketType]
            ],
            'marketProjection' => ['RUNNER_DESCRIPTION'],
            'maxResults' => 1
        ]);

        if (empty($markets['result'])) return null;

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
        return $this->request('placeOrders', [
            'marketId' => $marketId,
            'instructions' => [
                [
                    'selectionId' => $selectionId,
                    'handicap' => '0',
                    'orderType' => 'LIMIT',
                    'side' => 'BACK',
                    'limitOrder' => [
                        'size' => number_format($size, 2, '.', ''),
                        'price' => number_format($price, 2, '.', ''),
                        'persistenceType' => 'LAPSE'
                    ]
                ]
            ]
        ]);
    }
}
