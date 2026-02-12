<?php
// tennis-bet/app/Services/TennisBetfairService.php

namespace TennisApp\Services;

use TennisApp\Config\TennisConfig;

class TennisBetfairService
{
    private $appKey;
    private $sessionToken;
    private $apiUrl = 'https://api.betfair.com/exchange/betting/json-rpc/v1';

    public function __construct()
    {
        TennisConfig::init();
        // Use the same keys as the main app
        $this->appKey = TennisConfig::get('BETFAIR_APP_KEY_LIVE') ?: TennisConfig::get('BETFAIR_APP_KEY_DELAY');

        // Try to load the session token from the main app's data path
        $mainSessionFile = __DIR__ . '/../../../data/betfair_session.txt';
        if (file_exists($mainSessionFile)) {
            $this->sessionToken = trim(file_get_contents($mainSessionFile));
        }
    }

    public function getTennisLiveEvents()
    {
        return $this->request('listEvents', [
            'filter' => [
                'eventTypeIds' => [TennisConfig::TENNIS_EVENT_TYPE_ID],
                'inPlayOnly' => true
            ]
        ]);
    }

    public function getUpcomingTennisEvents($hours = 24)
    {
        $from = gmdate('Y-m-d\TH:i:s\Z', time());
        $to = gmdate('Y-m-d\TH:i:s\Z', time() + ($hours * 3600));

        return $this->request('listEvents', [
            'filter' => [
                'eventTypeIds' => [TennisConfig::TENNIS_EVENT_TYPE_ID],
                'marketStartTime' => ['from' => $from, 'to' => $to]
            ]
        ]);
    }

    public function getMarketCatalogues(array $eventIds)
    {
        return $this->request('listMarketCatalogue', [
            'filter' => [
                'eventIds' => $eventIds,
                'marketTypeCodes' => ['MATCH_ODDS']
            ],
            'maxResults' => 50,
            'marketProjection' => ['RUNNER_DESCRIPTION', 'EVENT', 'COMPETITION']
        ]);
    }

    public function getMarketBooks(array $marketIds)
    {
        return $this->request('listMarketBook', [
            'marketIds' => $marketIds,
            'priceProjection' => [
                'priceData' => ['EX_BEST_OFFERS'],
                'virtualise' => true
            ]
        ]);
    }

    private function request($method, $params)
    {
        if (!$this->sessionToken || !$this->appKey)
            return null;

        $payload = json_encode([
            "jsonrpc" => "2.0",
            "method" => "SportsAPING/v1.0/" . $method,
            "params" => $params,
            "id" => 1
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Application: {$this->appKey}",
            "X-Authentication: {$this->sessionToken}",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
