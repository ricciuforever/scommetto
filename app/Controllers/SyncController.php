<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Usage;
use App\Models\Bet;
use App\Services\FootballApiService;

class SyncController
{
    private $usageModel;
    private $apiService;

    public function __construct()
    {
        $this->usageModel = new Usage();
        $this->apiService = new FootballApiService();
    }

    public function getUsage()
    {
        header('Content-Type: application/json');
        $usage = $this->usageModel->getLatest();
        echo json_encode($usage);
    }

    public function sync()
    {
        header('Content-Type: application/json');

        // Logic to sync bets results
        // This would involve fetching results for pending bets from the API
        // For now, returning a status

        echo json_encode(['status' => 'sync_started', 'message' => 'Not fully implemented yet']);
    }
}
