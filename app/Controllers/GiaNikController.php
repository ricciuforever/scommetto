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
}
