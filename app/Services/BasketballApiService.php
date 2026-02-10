<?php
// app/Services/BasketballApiService.php

namespace App\Services;

use App\Config\Config;
use App\Models\Usage;

class BasketballApiService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('FOOTBALL_API_KEY'); // Using the same key usually works for API-Sports
        $this->baseUrl = Config::BASKETBALL_API_BASE_URL;
    }

    public function fetchLiveGames($params = [])
    {
        $endpoint = '/games?live=all&timezone=Europe/Rome';
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $endpoint .= "&$queryString";
        }
        return $this->request($endpoint);
    }

    public function fetchStandings($leagueId, $season, $teamId = null)
    {
        $url = "/standings?season=$season&league=$leagueId";
        if ($teamId)
            $url .= "&team=$teamId";
        return $this->request($url);
    }

    public function fetchGames($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/games?$queryString");
    }

    public function fetchH2H($h2h)
    {
        return $this->request("/games/headtohead?h2h=$h2h");
    }

    public function fetchTeams($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/teams?$queryString");
    }

    public function fetchGameTeamStatistics($gameId)
    {
        return $this->request("/games/statistics/teams?id=$gameId");
    }

    public function fetchGamePlayerStatistics($gameId)
    {
        return $this->request("/games/statistics/players?id=$gameId");
    }

    public function request($endpoint)
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $headers = [];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-apisports-key: " . $this->apiKey
        ]);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2)
                return $len;
            $headers[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }

        // Shared usage tracking logic
        try {
            $remaining = $headers['x-ratelimit-requests-remaining'] ?? $headers['x-ratelimit-remaining'] ?? null;
            if ($remaining !== null) {
                $usageModel = new Usage();
                $currentUsage = $usageModel->getLatest();
                $limit = $headers['x-ratelimit-requests-limit'] ?? (is_array($currentUsage) ? ($currentUsage['requests_limit'] ?? 100) : 100);
                $used = $headers['x-ratelimit-requests-used'] ?? $headers['x-ratelimit-used'] ?? ($limit - (int)$remaining);
                $usageModel->update((int)$used, (int)$remaining, (int)$limit);
            }
        } catch (\Throwable $e) {
            error_log("Usage Tracking Error (Basketball): " . $e->getMessage());
        }

        return json_decode($response, true);
    }
}
