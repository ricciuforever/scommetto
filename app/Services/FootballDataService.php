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
use App\Models\Coach;
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

    public function getCoach($teamId)
    {
        $model = new Coach();
        if ($model->needsRefresh($teamId)) {
            $res = $this->api->fetchCoach($teamId);
            if (!empty($res['response'])) {
                $model->save($res['response'][0], $teamId);
            }
        }
        return $model->getByTeam($teamId);
    }

    public function getSquad($teamId, $forceSync = false)
    {
        $model = new Player();
        // Check if there are any players for this team in the squads table
        $db = \App\Services\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM squads WHERE team_id = ?");
        $stmt->execute([$teamId]);
        $count = $stmt->fetchColumn();

        if ($forceSync || $count == 0) {
            $res = $this->api->fetchSquad($teamId);
            if (!empty($res['response'][0]['players'])) {
                foreach ($res['response'][0]['players'] as $p) {
                    $model->save($p);
                    $model->linkToSquad($teamId, $p, $p);
                }
            }
        }
        return $model->getByTeam($teamId);
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
     * Get matches for a specific date with caching
     */
    public function getFixturesByDate($date, $cacheSeconds = 3600)
    {
        $db = \App\Services\Database::getInstance()->getConnection();
        $stateKey = "last_date_sync_" . $date;
        $cacheFile = Config::DATA_PATH . "api_football_date_" . $date . ".json";

        $stmt = $db->prepare("SELECT updated_at FROM system_state WHERE `key` = ?");
        $stmt->execute([$stateKey]);
        $lastSync = $stmt->fetchColumn();

        if ($lastSync && (time() - strtotime($lastSync)) < $cacheSeconds && file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        $res = $this->api->request("/fixtures?date=$date&timezone=Europe/Rome");
        if (isset($res['response'])) {
            $fixtureModel = new Fixture();
            foreach ($res['response'] as $item) {
                $fixtureModel->save($item);
            }

            $sql = "INSERT INTO system_state (`key`, `value`, `updated_at`) VALUES (?, 'ok', CURRENT_TIMESTAMP) ";
            if (\App\Services\Database::getInstance()->isSQLite()) {
                $sql .= " ON CONFLICT(`key`) DO UPDATE SET updated_at = CURRENT_TIMESTAMP";
            } else {
                $sql .= " ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP";
            }
            $db->prepare($sql)->execute([$stateKey]);

            file_put_contents($cacheFile, json_encode($res));
            return $res;
        }

        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        return ['response' => []];
    }

    /**
     * Search a Betfair event in the fixture list using normalized names
     */
    public function searchInFixtureList($bfEventName, $fixtures, $mappedCountry = null)
    {
        // 1. Strip scores like "1-0", "0 - 0" if present in event name
        $name = preg_replace('/\d+\s*-\s*\d+/', ' v ', $bfEventName);

        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $name);
        if (count($bfTeams) < 2) {
            // Fallback: try splitting by '/' with optional spaces (Team1/Team2 or Team1 / Team2)
            $bfTeams = preg_split('/\s*\/\s*/', $name);
        }

        if (count($bfTeams) < 2) {
            // Last resort: split by ' - ' if it's there and not a score
            if (strpos($name, ' - ') !== false) {
                $bfTeams = explode(' - ', $name);
            }
        }

        if (count($bfTeams) < 2)
            return null;

        $bfHome = $this->normalizeTeamName($bfTeams[0]);
        $bfAway = $this->normalizeTeamName($bfTeams[1]);

        foreach ($fixtures as $item) {
            // Optional: prioritize by country if mappedCountry is provided
            if ($mappedCountry && isset($item['league']['country'])) {
                $apiCountry = $item['league']['country'];
                $countriesToMatch = is_array($mappedCountry) ? $mappedCountry : [$mappedCountry];
                if (!in_array($apiCountry, $countriesToMatch)) {
                    continue;
                }
            }

            $apiHome = $this->normalizeTeamName($item['teams']['home']['name']);
            $apiAway = $this->normalizeTeamName($item['teams']['away']['name']);

            if (
                ($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) ||
                ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))
            ) {
                return $item;
            }
        }

        // Fallback: if no match found with country filter, try WITHOUT country filter
        if ($mappedCountry) {
            return $this->searchInFixtureList($bfEventName, $fixtures, null);
        }

        return null;
    }

    /**
     * Normalize team name for better matching
     */
    public function normalizeTeamName($name)
    {
        // 0. Preliminary cleanup: remove content in parentheses (e.g., "(SdE)", "(KSA)")
        $name = preg_replace('/\s*\(.*?\)/', '', $name);
        // Replace '/' or '-' with space to handle split names or different conventions
        $name = str_replace(['/', '-', ' - '], ' ', $name);

        $name = strtolower($name);

        // 1. Transliterate common accented characters
        $chars = [
            'ä' => 'a',
            'ö' => 'o',
            'ü' => 'u',
            'ß' => 'ss',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ø' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'ý' => 'y',
            'ÿ' => 'y'
        ];
        $name = strtr($name, $chars);

        // 2. Common abbreviations / Core name simplification
        $replacements = [
            'man' => 'manchester',
            'man.' => 'manchester',
            'utd' => 'united',
            'manchester united' => 'manchester', // simplify to core
            'manchester city' => 'manchester',
            'st' => 'saint',
            'st.' => 'saint',
            'int' => 'inter',
            'int.' => 'inter',
            'ath' => 'athletic',
            'atl' => 'atletico',
            'at.' => 'atletico',
            'ind' => 'independiente',
            'ind.' => 'independiente',
            'dep' => 'deportivo',
            'dep.' => 'deportivo',
            'sg' => 'saint germain',
            'nottm' => 'nottingham',
            'wolves' => 'wolverhampton',
            'spurs' => 'tottenham',
            'boro' => 'middlesbrough',
            'qpr' => 'queens park rangers',
            'sheff' => 'sheffield',
            'forest' => 'nottingham',
            'wednesday' => 'wed',
            'mk' => 'milton keynes',
            'alder' => 'aldershot',
            'dhamk' => 'damac',
            'taawoun' => 'taawon',
            'uniao' => 'union',
            'athletico' => 'atletico'
        ];
        foreach ($replacements as $search => $replace) {
            $name = preg_replace('/\b' . preg_quote($search, '/') . '\b/i', $replace, $name);
        }

        // 3. Remove common prefixes/suffixes and noise words
        $remove = [
            'fc',
            'f.c.',
            'united',
            'city',
            'town',
            'real',
            'atlético',
            'atletico',
            'u23',
            'u21',
            'u19',
            'u20',
            'u18',
            'u17',
            'u16',
            'youth',
            'primavera',
            'women',
            'women\'s',
            'womens',
            'ladies',
            'girl',
            'girls',
            'boy',
            'boys',
            'donne',
            'femminile',
            'reserves',
            'reserve',
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
            'nk',
            'hsk',
            'acs',
            'csc',
            'csm',
            'cs',
            'afc',
            'pfc',
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
            'cp',
            'ballklubb',
            'club',
            'sport',
            'v.',
            'vs',
            'ii',
            ' b',
            ' w',
            'lisbon',
            'lisboa',
            'utd',
            'unam',
            'u.n.a.m.',
            'universidad',
            'univ',
            'catolica',
            'nacional',
            'municipal',
            'y',
            'de',
            'la',
            'el',
            'da',
            'do',
            'dos',
            'das',
            'van',
            'der',
            'di',
            'e',
            'ksa',
            'sde',
            'sp',
            'ba',
            'rj',
            'mg',
            'rs',
            'ce',
            'pe',
            'sc',
            'go',
            'pr',
            'pb',
            'al',
            'rn',
            'pi',
            'mt',
            'ms',
            'se',
            'pa',
            'to',
            'df',
            'ro',
            'ac',
            'rr',
            'ap',
            'buraidah',
            'riyadh',
            'jeddah',
            'damman'
        ];

        $tempName = $name;
        foreach ($remove as $r) {
            $tempName = preg_replace('/\b' . preg_quote($r, '/') . '\b/i', '', $tempName);
        }

        $tempName = trim(preg_replace('/\s+/', ' ', $tempName));
        if (!empty($tempName)) {
            $name = $tempName;
        }

        // 4. Final cleanup: remove non-alphanumeric (except spaces), collapse multiple spaces
        $name = preg_replace('/[^a-z0-9 ]/', '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Robust matching between two normalized names
     */
    public function isMatch($n1, $n2)
    {
        if (empty($n1) || empty($n2))
            return false;

        // 1. Exact match or substring
        if ($n1 === $n2 || strpos($n1, $n2) !== false || strpos($n2, $n1) !== false) {
            return true;
        }

        // 2. Word-based matching (e.g., "Gimnasia Jujuy" vs "Gimnasia y Esgrima de Jujuy")
        $w1 = explode(' ', $n1);
        $w2 = explode(' ', $n2);
        if (count($w1) >= 2 || count($w2) >= 2) {
            $shorter = (count($w1) < count($w2)) ? $w1 : $w2;
            $longer = (count($w1) < count($w2)) ? $w2 : $w1;

            $matchCount = 0;
            foreach ($shorter as $w) {
                if (in_array($w, $longer))
                    $matchCount++;
            }
            // If all words of shorter exist in longer (min 2 words)
            if ($matchCount >= count($shorter) && count($shorter) >= 2) {
                return true;
            }

            // If at least 2 words match and they are at least 50% of the longer name
            if ($matchCount >= 2 && $matchCount >= (count($longer) * 0.5)) {
                return true;
            }
        }

        // 3. Adaptive Levenshtein threshold
        $len1 = strlen($n1);
        $len2 = strlen($n2);
        $threshold = ($len1 > 10 && $len2 > 10) ? 4 : 3;

        if (levenshtein($n1, $n2) < $threshold) {
            return true;
        }

        return false;
    }
}
