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

        if (!$fixture || $model->needsLeagueRefresh($fixture['league_id'] ?? 0, Config::getCurrentSeason(), 1)) {
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
                $pred = $res['response'][0];
                $model->save($fixtureId, $pred['predictions'], $pred['comparison']);
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
                    $model->save($fixtureId, $teamStats['team']['id'], $teamStats['players']);
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
        if (!$season) $season = Config::getCurrentSeason();
        $model = new Player();
        // Custom check for player refresh if model doesn't have it
        $db = \App\Services\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT last_updated FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $last = $stmt->fetchColumn();

        if (!$last || (time() - strtotime($last)) > 86400) {
             $res = $this->api->fetchPlayer(['id' => $id, 'season' => $season]);
             if (!empty($res['response'])) {
                 $model->save($res['response'][0]);
                 (new \App\Models\PlayerStatistics())->save($id, $res['response'][0]['statistics'][0]['team']['id'], $season, $res['response'][0]['statistics']);
             }
        }
        return $model->getById($id);
    }
}
