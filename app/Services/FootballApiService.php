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

    public function fetchStandings($leagueId, $season)
    {
        return $this->request("/standings?league=$leagueId&season=$season");
    }

    public function fetchTeam($id)
    {
        return $this->request("/teams?id=$id");
    }

    public function fetchCoach($teamId)
    {
        return $this->request("/coaches?team=$teamId");
    }

    public function fetchSquad($teamId)
    {
        return $this->request("/players/squads?team=$teamId");
    }

    public function fetchPlayer($id, $season = 2024)
    {
        return $this->request("/players?id=$id&season=$season");
    }


    public function fetchPredictions($fixtureId)
    {
        return $this->request("/predictions?fixture=$fixtureId");
    }

    public function request($endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: v3.football.api-sports.io",
            "x-rapidapi-key: " . $this->apiKey
        ]);

        // Function to capture headers
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

        // Update Usage if headers are present
        $used = $headers['x-ratelimit-requests-used'] ?? $headers['x-ratelimit-used'] ?? $headers['x-ratelimit-requests-limit-used'] ?? null;
        $remaining = $headers['x-ratelimit-requests-remaining'] ?? $headers['x-ratelimit-remaining'] ?? null;

        if ($used !== null || $remaining !== null) {
            $currentUsed = $used ?? (7500 - (int) $remaining);
            $currentRem = $remaining ?? (7500 - (int) $used);
            (new Usage())->update((int) $currentUsed, (int) $currentRem);
        }

        return json_decode($response, true);
    }
}
