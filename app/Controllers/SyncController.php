<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Usage;
use App\Models\Bet;
use App\Models\Analysis;
use App\Services\FootballApiService;
use App\Services\GeminiService;

class SyncController
{
    private $usageModel;
    private $betModel;
    private $analysisModel;
    private $apiService;
    private $geminiService;

    public function __construct()
    {
        $this->usageModel = new Usage();
        $this->betModel = new Bet();
        $this->analysisModel = new Analysis();
        $this->apiService = new FootballApiService();
        $this->geminiService = new GeminiService();
    }

    public function getUsage()
    {
        header('Content-Type: application/json');
        echo json_encode($this->usageModel->getLatest());
    }

    /**
     * Main bot loop to be called via Cron every minute
     */
    public function sync()
    {
        header('Content-Type: application/json');

        $results = [
            'bot_status' => 'idle',
            'scanned' => 0,
            'analyzed' => 0,
            'bets_placed' => 0,
            'settled' => 0
        ];

        // 1. Check existing pending bets for results (Settlement)
        $results['settled'] = $this->checkSettleBets();

        // 2. Scan live matches for new opportunities
        $live = $this->apiService->fetchLiveMatches();
        $matches = $live['response'] ?? [];
        $results['scanned'] = count($matches);

        $usage = $this->usageModel->getLatest();
        if ($usage && $usage['requests_remaining'] < 20) {
            $results['bot_status'] = 'paused_low_quota';
            echo json_encode($results);
            return;
        }

        foreach ($matches as $m) {
            $fid = $m['fixture']['id'];
            $elapsed = $m['fixture']['status']['elapsed'] ?? 0;

            // Only analyze if match is between 10' and 80'
            if ($elapsed < 10 || $elapsed > 80)
                continue;

            // Don't analyze if already pending
            if ($this->betModel->isPending($fid))
                continue;

            // Don't analyze if checked in the last 45 mins
            if ($this->analysisModel->wasRecentlyChecked($fid))
                continue;

            // ANALYZE!
            $results['analyzed']++;
            $prediction = $this->geminiService->analyze($m);
            $this->analysisModel->log($fid, $prediction);

            // Extract JSON from prediction
            if (preg_match('/```json\n([\s\S]*?)\n```/', $prediction, $matches_json)) {
                $betData = json_decode($matches_json[1], true);
                if ($betData) {
                    $betData['fixture_id'] = $fid;
                    $betData['match'] = $m['teams']['home']['name'] . ' vs ' . $m['teams']['away']['name'];
                    $this->betModel->create($betData);
                    $results['bets_placed']++;
                }
            }

            // Limit to 2 analyzes per run to avoid timeout/quota spikes
            if ($results['analyzed'] >= 2)
                break;
        }

        $results['bot_status'] = 'completed';
        echo json_encode($results);
    }

    private function checkSettleBets()
    {
        $pending = array_filter($this->betModel->getAll(), function ($b) {
            return $b['status'] === 'pending';
        });

        $settledCount = 0;
        $betSettler = new \App\Services\BetSettler();

        foreach ($pending as $bet) {
            // Check if match is finished (fetch details)
            $details = $this->apiService->fetchFixtureDetails($bet['fixture_id']);
            $fixture = $details['response'][0] ?? null;

            if ($fixture && in_array($fixture['fixture']['status']['short'], ['FT', 'AET', 'PEN'])) {
                $betSettler->processSettlement($bet, $fixture);
                $settledCount++;
            }
        }
        return $settledCount;
    }
}
