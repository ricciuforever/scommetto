<?php
// app/Services/TennisApiService.php

namespace App\Services;

use App\Config\Config;
use App\Models\Usage;

class TennisApiService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('FOOTBALL_API_KEY');
        $this->baseUrl = Config::TENNIS_API_BASE_URL;
    }

    public function fetchLiveMatches($params = [])
    {
        $endpoint = '/fixtures?live=all';
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $endpoint .= "&$queryString";
        }
        return $this->request($endpoint);
    }

    public function fetchStandings($leagueId, $season)
    {
        return $this->request("/standings?league=$leagueId&season=$season");
    }

    public function fetchH2H($h2h)
    {
        return $this->request("/fixtures/headtohead?h2h=$h2h");
    }

    public function request($endpoint)
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        $headers = [];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: v1.tennis.api-sports.io",
            "x-rapidapi-key: " . $this->apiKey
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
            error_log("Usage Tracking Error (Tennis): " . $e->getMessage());
        }

        return json_decode($response, true);
    }
}
