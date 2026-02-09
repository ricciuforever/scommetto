<?php
// app/Controllers/GiaNikController.php

namespace App\Controllers;

use App\Config\Config;

class GiaNikController
{
    public function index()
    {
        $file = __DIR__ . '/../Views/gianik_live_page.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "<h1>GiaNik Live</h1><p>Caricamento in corso...</p>";
        }
    }

    public function live()
    {
        try {
            $liveMatches = [];
            $cacheFile = Config::DATA_PATH . 'betfair_live.json';

            if (file_exists($cacheFile)) {
                $content = file_get_contents($cacheFile);
                if ($content) {
                    $data = json_decode($content, true);
                    $liveMatches = $data['response'] ?? [];
                }
            }

            // Account and Orders
            $bf = new \App\Services\BetfairService();
            $account = ['available' => 0, 'exposure' => 0];
            $orders = [];

            if ($bf->isConfigured()) {
                $funds = $bf->getFunds();
                if (isset($funds['result'])) $funds = $funds['result'];
                $account['available'] = $funds['availableToBetBalance'] ?? 0;
                $account['exposure'] = abs($funds['exposure'] ?? 0);

                $ordersRes = $bf->getCurrentOrders();
                $orders = $ordersRes['currentOrders'] ?? [];

                $settledRes = $bf->getClearedOrders();
                $history = $settledRes['clearedOrders'] ?? [];
            }

            // Group by sport
            $groupedMatches = [];
            foreach ($liveMatches as $m) {
                $sport = $m['sport'] ?? 'Altro';
                $groupedMatches[$sport][] = $m;
            }

            // Sort sports by number of events
            uksort($groupedMatches, function($a, $b) use ($groupedMatches) {
                return count($groupedMatches[$b]) <=> count($groupedMatches[$a]);
            });

            require __DIR__ . '/../Views/partials/gianik_live.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore GiaNik Live: ' . $e->getMessage() . '</div>';
        }
    }

    public function analyze($marketId)
    {
        try {
            $bf = new \App\Services\BetfairService();
            $cacheFile = Config::DATA_PATH . 'betfair_live.json';
            $event = null;

            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                foreach ($data['response'] ?? [] as $m) {
                    if ($m['marketId'] === $marketId) {
                        $event = $m;
                        break;
                    }
                }
            }

            if (!$event) {
                echo '<div class="glass p-10 rounded-3xl text-center border-danger/20 text-danger uppercase font-black italic">Evento non trovato.</div>';
                return;
            }

            $funds = $bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];

            $available = $funds['availableToBetBalance'] ?? 0;
            $exposure = abs($funds['exposure'] ?? 0);

            $balance = [
                'available_balance' => $available,
                'current_portfolio' => $available + $exposure
            ];

            $gemini = new \App\Services\GeminiService();
            // Passiamo l'opzione 'is_gianik' per segnalare che è un'analisi singola da dashboard multi-sport
            $predictionRaw = $gemini->analyze([$event], array_merge($balance, ['is_gianik' => true]));

            // Parsing della risposta JSON se presente
            $analysis = [];
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                $analysis = json_decode($matches[1], true);
            }

            $reasoning = trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));

            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore Analisi: ' . $e->getMessage() . '</div>';
        }
    }
}
