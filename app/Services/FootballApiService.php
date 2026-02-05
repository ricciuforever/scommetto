<?php
// app/Services/FootballApiService.php

namespace App\Services;

use App\Config\Config;
use App\Models\Usage;

class FootballApiService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = Config::get('FOOTBALL_API_KEY');
        $this->baseUrl = Config::FOOTBALL_API_BASE_URL;
    }

    public function fetchLiveMatches()
    {
        return $this->request('/fixtures?live=all');
    }

    public function fetchFixtureDetails($id)
    {
        return $this->request("/fixtures?id=$id");
    }

    private function request($endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: v3.football.api-sports.io",
            "x-rapidapi-key: " . $this->apiKey
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // If we needed headers, we'd use curl_setopt($ch, CURLOPT_HEADER, true);

        $error = curl_error($ch);

        // Check headers for usage
        // Note: To get headers properly we might need a slightly more complex curl setup
        // For now, let's just get the body.

        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }

        $data = json_decode($response, true);

        // In a real scenario, we'd extract usage from headers here.
        // Assuming we have a way to get them:
        // (new Usage())->update($used, $remaining);

        return $data;
    }
}
