<?php
// app/Controllers/MatchController.php

namespace App\Controllers;

use App\Services\FootballApiService;
use App\Services\GeminiService;
use App\Config\Config;

class MatchController
{
    private $apiService;
    private $geminiService;

    public function __construct()
    {
        $this->apiService = new FootballApiService();
        $this->geminiService = new GeminiService();
    }

    public function index()
    {
        // This will serve the frontend (index.html)
        $file = __DIR__ . '/../Views/main.php';
        if (file_exists($file)) {
            require $file;
        } else {
            // Fallback to a simple message if view doesn't exist yet
            echo "<h1>Scommetto - Area Live</h1><p>Caricamento in corso...</p>";
        }
    }

    public function getLive()
    {
        header('Content-Type: application/json');

        // Use cache if it's fresh (e.g., 60 seconds)
        $cacheFile = Config::LIVE_DATA_FILE;
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
            echo file_get_contents($cacheFile);
            return;
        }

        $data = $this->apiService->fetchLiveMatches();
        if (!isset($data['error'])) {
            file_put_contents($cacheFile, json_encode($data));
        }

        echo json_encode($data);
    }

    public function analyze($id)
    {
        header('Content-Type: application/json');

        $liveData = json_decode(file_exists(Config::LIVE_DATA_FILE) ? file_get_contents(Config::LIVE_DATA_FILE) : '{"response":[]}', true);
        $match = null;

        foreach ($liveData['response'] ?? [] as $m) {
            if ($m['fixture']['id'] == $id) {
                $match = $m;
                break;
            }
        }

        if (!$match) {
            echo json_encode(['error' => 'Match not found in live data']);
            return;
        }

        $prediction = $this->geminiService->analyze($match);

        // SAVE ANALYSIS TO DB
        try {
            $analysisModel = new \App\Models\Analysis();
            $analysisModel->log($id, $prediction);
        } catch (\Exception $e) {
            // Log error but don't stop the response
            error_log("Error saving analysis: " . $e->getMessage());
        }

        echo json_encode([
            'fixture_id' => $id,
            'prediction' => $prediction,
            'match' => $match
        ]);
    }
}
