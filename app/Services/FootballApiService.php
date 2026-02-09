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

    public function fetchLiveMatches($params = [])
    {
        $endpoint = '/fixtures?live=all';
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $endpoint .= "&$queryString";
        }
        return $this->request($endpoint);
    }

    public function fetchCountries()
    {
        return $this->request('/countries');
    }

    public function fetchSeasons()
    {
        return $this->request('/leagues/seasons');
    }

    public function fetchPlayersSeasons($playerId = null)
    {
        $url = "/players/seasons";
        if ($playerId) {
            $url .= "?player=$playerId";
        }
        return $this->request($url);
    }

    public function fetchLeagues($params = [])
    {
        $queryString = http_build_query($params);
        return $this->request("/leagues?$queryString");
    }

    public function fetchLeaguesRounds($leagueId, $season, $current = false)
    {
        $url = "/fixtures/rounds?league=$leagueId&season=$season";
        if ($current)
            $url .= "&current=true";
        return $this->request($url);
    }

    public function fetchLeaguesFixtures($leagueId, $season)
    {
        return $this->request("/fixtures?league=$leagueId&season=$season");
    }

    public function fetchH2H($h2h)
    {
        return $this->request("/fixtures/headtohead?h2h=$h2h");
    }

    public function fetchFixtureStatistics($fixtureId, $teamId = null)
    {
        $url = "/fixtures/statistics?fixture=$fixtureId";
        if ($teamId)
            $url .= "&team=$teamId";
        return $this->request($url);
    }

    public function fetchFixtureEvents($fixtureId, $teamId = null, $playerId = null, $type = null)
    {
        $url = "/fixtures/events?fixture=$fixtureId";
        if ($teamId)
            $url .= "&team=$teamId";
        if ($playerId)
            $url .= "&player=$playerId";
        if ($type)
            $url .= "&type=$type";
        return $this->request($url);
    }

    public function fetchFixtureLineups($fixtureId, $teamId = null, $playerId = null)
    {
        $url = "/fixtures/lineups?fixture=$fixtureId";
        if ($teamId)
            $url .= "&team=$teamId";
        if ($playerId)
            $url .= "&player=$playerId";
        return $this->request($url);
    }

    public function fetchFixturePlayerStatistics($fixtureId, $teamId = null)
    {
        $url = "/fixtures/players?fixture=$fixtureId";
        if ($teamId)
            $url .= "&team=$teamId";
        return $this->request($url);
    }

    public function fetchFixtureInjuries($fixtureId)
    {
        return $this->request("/injuries?fixture=$fixtureId");
    }

    public function fetchFixtureDetails($id)
    {
        return $this->request("/fixtures?id=$id");
    }

    public function fetchStandings($leagueId, $season, $teamId = null)
    {
        $url = "/standings?season=$season";
        if ($leagueId)
            $url .= "&league=$leagueId";
        if ($teamId)
            $url .= "&team=$teamId";
        return $this->request($url);
    }

    public function fetchTeam($id)
    {
        return $this->request("/teams?id=$id");
    }

    public function fetchTeams($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/teams?$queryString");
    }

    public function fetchCoach($teamId)
    {
        return $this->request("/coaches?team=$teamId");
    }

    public function fetchSquad($teamId)
    {
        return $this->request("/players/squads?team=$teamId");
    }

    public function fetchTeamSeasons($teamId)
    {
        return $this->request("/teams/seasons?team=$teamId");
    }

    public function fetchTeamCountries()
    {
        return $this->request("/teams/countries");
    }

    public function fetchTeamStatistics($teamId, $leagueId, $season, $date = null)
    {
        $url = "/teams/statistics?team=$teamId&league=$leagueId&season=$season";
        if ($date)
            $url .= "&date=$date";
        return $this->request($url);
    }

    public function fetchVenues($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/venues?$queryString");
    }

    public function fetchPlayer($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/players?$queryString");
    }

    public function fetchTopScorers($leagueId, $season)
    {
        return $this->request("/players/topscorers?league=$leagueId&season=$season");
    }

    public function fetchTopAssists($leagueId, $season)
    {
        return $this->request("/players/topassists?league=$leagueId&season=$season");
    }

    public function fetchTopYellowCards($leagueId, $season)
    {
        return $this->request("/players/topyellowcards?league=$leagueId&season=$season");
    }

    public function fetchTopRedCards($leagueId, $season)
    {
        return $this->request("/players/topredcards?league=$leagueId&season=$season");
    }


    public function fetchPredictions($fixtureId)
    {
        return $this->request("/predictions?fixture=$fixtureId");
    }

    public function fetchTransfers($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/transfers?$queryString");
    }

    public function fetchTrophies($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/trophies?$queryString");
    }

    public function fetchSidelined($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/sidelined?$queryString");
    }

    public function fetchLiveOdds($params = [])
    {
        $endpoint = "/odds/live";
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $endpoint .= "?$queryString";
        }
        return $this->request($endpoint);
    }

    public function fetchLiveOddsBets()
    {
        return $this->request("/odds/live/bets");
    }

    public function fetchOdds($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/odds?$queryString");
    }

    public function fetchOddsMapping($params = [])
    {
        $queryString = http_build_query($params);
        return $this->request("/odds/mapping?$queryString");
    }

    public function fetchBookmakers()
    {
        return $this->request("/odds/bookmakers");
    }

    public function fetchBets()
    {
        return $this->request("/odds/bets");
    }

    public function fetchPlayerProfiles($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/players/profiles?$queryString");
    }

    public function fetchPlayers($params)
    {
        $queryString = http_build_query($params);
        return $this->request("/players?$queryString");
    }

    public function fetchStatus()
    {
        return $this->request("/status");
    }

    public function request($endpoint)
    {
        // Ensure endpoint starts with / if not present (though usually is) and baseUrl doesn't end with /
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

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
        try {
            $limit = $headers['x-ratelimit-requests-limit'] ?? null;
            $used = $headers['x-ratelimit-requests-used'] ?? $headers['x-ratelimit-used'] ?? $headers['x-ratelimit-requests-limit-used'] ?? null;
            $remaining = $headers['x-ratelimit-requests-remaining'] ?? $headers['x-ratelimit-remaining'] ?? null;

            if ($remaining !== null || $used !== null) {
                $usageModel = new Usage();
                $currentUsage = $usageModel->getLatest();

                $limitVal = $limit ?? (is_array($currentUsage) ? ($currentUsage['requests_limit'] ?? 75000) : 75000);
                $currentLimit = (int) $limitVal;

                $currentRem = (int) ($remaining ?? ($currentLimit - (int) ($used ?? 0)));
                $currentUsed = (int) ($used ?? ($currentLimit - $currentRem));

                $usageModel->update($currentUsed, $currentRem, $limit !== null ? (int) $limit : null);
            }
        } catch (\Throwable $e) {
            // Silently ignore usage update errors (e.g. table missing) to avoid 500s
            error_log("Usage Tracking Error: " . $e->getMessage());
        }

        return json_decode($response, true);
    }
}
