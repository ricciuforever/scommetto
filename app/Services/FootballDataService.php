<?php
// app/Services/FootballDataService.php

namespace App\Services;

use App\Services\FootballApiService;
use App\Models\Fixture;
use App\Models\FixtureEvent;
use App\Models\FixtureStatistics;
use App\Models\FixturePrediction;
use App\Models\FixtureLineup;
use App\Models\FixturePlayerStatistics;
use App\Models\Team;
use App\Models\Venue;
use App\Models\Standing;
use App\Models\H2H;
use App\Models\Player;
use App\Config\Config;

class FootballDataService
{
    private $api;

    public function __construct()
    {
        $this->api = new FootballApiService();
    }

    public function getFixtureDetails($id)
    {
        $model = new Fixture();
        $fixture = $model->getById($id);

        // Usa needsRefresh() intelligente basato su status (LIVE=1min, Scheduled=1h, Finished=24h)
        if (!$fixture || $model->needsRefresh($id)) {
            $res = $this->api->fetchFixtureDetails($id);
            if (!empty($res['response'])) {
                foreach ($res['response'] as $item) {
                    $model->save($item);
                }
                $fixture = $model->getById($id);
            }
        }
        return $fixture;
    }

    public function getFixtureEvents($fixtureId, $statusShort = 'NS')
    {
        $model = new FixtureEvent();
        if ($model->needsRefresh($fixtureId, $statusShort)) {
            $res = $this->api->fetchFixtureEvents($fixtureId);
            if (isset($res['response'])) {
                $model->deleteByFixture($fixtureId);
                foreach ($res['response'] as $ev) {
                    $model->save($fixtureId, $ev);
                }
            }
        }
        return $model->getByFixture($fixtureId);
    }

    public function getFixtureStatistics($fixtureId, $statusShort = 'NS')
    {
        $model = new FixtureStatistics();
        if ($model->needsRefresh($fixtureId, $statusShort)) {
            $res = $this->api->fetchFixtureStatistics($fixtureId);
            if (!empty($res['response'])) {
                foreach ($res['response'] as $s) {
                    $model->save($fixtureId, $s['team']['id'], $s['statistics']);
                }
            }
        }
        return $model->getByFixture($fixtureId);
    }

    public function getFixturePredictions($fixtureId, $statusShort = 'NS')
    {
        $model = new FixturePrediction();
        if ($model->needsRefresh($fixtureId, $statusShort)) {
            $res = $this->api->fetchPredictions($fixtureId);
            if (!empty($res['response'])) {
                // Salviamo l'INTERO response[0] che include predictions, comparison, teams, h2h
                $model->save($fixtureId, $res['response'][0]);
            }
        }
        return $model->getByFixture($fixtureId);
    }

    public function getFixtureLineups($fixtureId, $statusShort = 'NS')
    {
        $model = new FixtureLineup();
        if ($model->needsRefresh($fixtureId, $statusShort)) {
            $res = $this->api->fetchFixtureLineups($fixtureId);
            if (!empty($res['response'])) {
                foreach ($res['response'] as $lineup) {
                    $model->save($fixtureId, $lineup['team']['id'], $lineup);
                }
            }
        }
        return $model->getByFixture($fixtureId);
    }

    public function getFixturePlayerStatistics($fixtureId, $statusShort = 'NS')
    {
        $model = new FixturePlayerStatistics();
        if ($model->needsRefresh($fixtureId, $statusShort)) {
            $res = $this->api->fetchFixturePlayerStatistics($fixtureId);
            if (!empty($res['response'])) {
                foreach ($res['response'] as $teamStats) {
                    $teamId = $teamStats['team']['id'];
                    foreach ($teamStats['players'] as $playerData) {
                        $model->save($fixtureId, $teamId, $playerData['player']['id'], $playerData);
                    }
                }
            }
        }
        return $model->getByFixture($fixtureId);
    }

    public function getTeamDetails($teamId)
    {
        $model = new Team();
        if ($model->needsRefresh($teamId)) {
            $res = $this->api->fetchTeam($teamId);
            if (!empty($res['response'])) {
                $model->save($res['response'][0]);
            }
        }
        return $model->getById($teamId);
    }

    public function getStandings($leagueId, $season, $teamId = null)
    {
        $model = new Standing();
        if ($model->needsRefresh($leagueId, $season)) {
            $res = $this->api->fetchStandings($leagueId, $season, $teamId);
            if (!empty($res['response'][0]['league']['standings'])) {
                foreach ($res['response'][0]['league']['standings'] as $group) {
                    foreach ($group as $item) {
                        $model->save($leagueId, $season, $item);
                    }
                }
                // Update sync timestamp
                $db = \App\Services\Database::getInstance()->getConnection();
                $sql = "INSERT INTO league_seasons (league_id, year, last_standings_sync) VALUES (?, ?, CURRENT_TIMESTAMP) ";
                if (\App\Services\Database::getInstance()->isSQLite()) {
                    $sql .= " ON CONFLICT(league_id, year) DO UPDATE SET last_standings_sync = CURRENT_TIMESTAMP";
                } else {
                    $sql .= " ON DUPLICATE KEY UPDATE last_standings_sync = CURRENT_TIMESTAMP";
                }
                $stmt = $db->prepare($sql);
                $stmt->execute([$leagueId, $season]);
            }
        }

        if ($teamId) {
            $stmt = \App\Services\Database::getInstance()->getConnection()->prepare("SELECT s.*, t.name as team_name, t.logo as team_logo FROM standings s JOIN teams t ON s.team_id = t.id WHERE s.league_id = ? AND s.season = ? AND s.team_id = ?");
            $stmt->execute([$leagueId, $season, $teamId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $model->getByLeagueAndSeason($leagueId, $season);
    }

    public function getH2H($team1Id, $team2Id)
    {
        $model = new H2H();
        if ($model->needsRefresh($team1Id, $team2Id)) {
            $res = $this->api->fetchH2H($team1Id . '-' . $team2Id);
            if (!empty($res['response'])) {
                $model->save($team1Id, $team2Id, $res['response']);
            }
        }
        return $model->get($team1Id, $team2Id);
    }

    public function getPlayer($id, $season = null)
    {
        if (!$season)
            $season = Config::getCurrentSeason();
        $model = new Player();
        // Custom check for player refresh if model doesn't have it
        $db = \App\Services\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT last_updated FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $last = $stmt->fetchColumn();

        if (!$last || (time() - strtotime($last)) > 86400) {
            $res = $this->api->fetchPlayer(['id' => $id, 'season' => $season]);
            if (!empty($res['response'])) {
                $model->save($res['response'][0]['player']);
                $stats = $res['response'][0]['statistics'][0];
                (new \App\Models\PlayerStatistics())->save($id, $stats['team']['id'], $stats['league']['id'], $season, $res['response'][0]['statistics']);
            }
        }
        return $model->getById($id);
    }

    /**
     * Get live matches with centralized caching and DB sync
     */
    public function getLiveMatches($params = [], $cacheSeconds = 60)
    {
        $db = \App\Services\Database::getInstance()->getConnection();

        $paramsHash = md5(json_encode($params));
        $stateKey = "last_live_sync_" . $paramsHash;
        $cacheFile = Config::DATA_PATH . "api_football_live_" . $paramsHash . ".json";

        // 1. Check last sync from DB
        $stmt = $db->prepare("SELECT updated_at FROM system_state WHERE `key` = ?");
        $stmt->execute([$stateKey]);
        $lastSync = $stmt->fetchColumn();

        $needsSync = true;

        if ($lastSync && (time() - strtotime($lastSync)) < $cacheSeconds && file_exists($cacheFile)) {
            $needsSync = false;
        }

        if ($needsSync) {
            $res = $this->api->fetchLiveMatches($params);
            if (isset($res['response'])) {
                // Update DB Fixtures
                $fixtureModel = new Fixture();
                foreach ($res['response'] as $item) {
                    $fixtureModel->save($item);
                }

                // Update system_state
                $sql = "INSERT INTO system_state (`key`, `value`, `updated_at`) VALUES (?, 'ok', CURRENT_TIMESTAMP) ";
                if (\App\Services\Database::getInstance()->isSQLite()) {
                    $sql .= " ON CONFLICT(`key`) DO UPDATE SET updated_at = CURRENT_TIMESTAMP";
                } else {
                    $sql .= " ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP";
                }
                $db->prepare($sql)->execute([$stateKey]);

                // Save to file cache for quick access
                file_put_contents($cacheFile, json_encode($res));
                return $res;
            }
        }

        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        return ['response' => []];
    }

    /**
     * Search a Betfair event in the fixture list using normalized names
     */
    public function searchInFixtureList($bfEventName, $fixtures)
    {
        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $bfEventName);
        if (count($bfTeams) < 2)
            return null;
        $bfHome = $this->normalizeTeamName($bfTeams[0]);
        $bfAway = $this->normalizeTeamName($bfTeams[1]);
        foreach ($fixtures as $item) {
            $apiHome = $this->normalizeTeamName($item['teams']['home']['name']);
            $apiAway = $this->normalizeTeamName($item['teams']['away']['name']);
            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) || ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome)))
                return $item;
        }
        return null;
    }

    /**
     * Normalize team name for better matching
     */
    public function normalizeTeamName($name)
    {
        $name = strtolower($name);
        $replacements = [
            'man ' => 'manchester ',
            'man utd' => 'manchester united',
            'man city' => 'manchester city',
            'st ' => 'saint ',
            'int ' => 'inter ',
            'ath ' => 'athletic ',
            'atl ' => 'atletico ',
            'de ' => ' ',
            'la ' => ' '
        ];
        foreach ($replacements as $search => $replace)
            $name = str_replace($search, $replace, $name);

        $remove = [
            'fc',
            'united',
            'city',
            'town',
            'real',
            'atlético',
            'atletico',
            'inter',
            'u23',
            'u21',
            'u19',
            'women',
            'donne',
            'femminile',
            'sports',
            'sc',
            'ac',
            'as',
            'cf',
            'rc',
            'de',
            'rs',
            'bk',
            'fk',
            'ff',
            'if',
            'is',
            'sk',
            'sv',
            'spvgg',
            'bsc',
            'tsv',
            'vfb',
            'vfl',
            'utd',
            'ballklubb'
        ];
        foreach ($remove as $r)
            $name = preg_replace('/\b' . preg_quote($r, '/') . '\b/i', '', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Robust matching between two normalized names
     */
    public function isMatch($n1, $n2)
    {
        if (empty($n1) || empty($n2))
            return false;
        if (strpos($n1, $n2) !== false || strpos($n2, $n1) !== false)
            return true;
        if (levenshtein($n1, $n2) < 3)
            return true;
        return false;
    }
}
