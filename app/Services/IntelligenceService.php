<?php
// app/Services/IntelligenceService.php

namespace App\Services;

use App\Models\Fixture;
use App\Models\TeamStats;
use App\Models\Standing;
use App\Models\Prediction;

class IntelligenceService
{
    private $fixtureModel;
    private $statsModel;
    private $standingModel;
    private $predictionModel;

    public function __construct()
    {
        $this->fixtureModel = new Fixture();
        $this->statsModel = new TeamStats();
        $this->standingModel = new Standing();
        $this->predictionModel = new Prediction();
    }

    /**
     * Gathers all local data for a match to provide deep context to Gemini
     */
    public function getDeepContext($fixture_id, $home_id, $away_id, $league_id, $season = 2024)
    {
        $context = [
            'home' => [
                'recent_matches' => $this->fixtureModel->getTeamRecent($home_id),
                'stats' => $this->statsModel->get($home_id, $league_id, $season),
                'standing' => $this->standingModel->getByTeamAndLeague($home_id, $league_id)
            ],
            'away' => [
                'recent_matches' => $this->fixtureModel->getTeamRecent($away_id),
                'stats' => $this->statsModel->get($away_id, $league_id, $season),
                'standing' => $this->standingModel->getByTeamAndLeague($away_id, $league_id)
            ],
            'api_prediction' => $this->predictionModel->getByFixtureId($fixture_id)
        ];

        return $context;
    }
}
