<?php
// app/Controllers/SyncController.php

namespace App\Controllers;

use App\Models\Bet;
use App\Models\Analysis;
use App\Services\GeminiService;
use App\Services\BetfairService;
use App\Config\Config;
use App\Services\Database;
use PDO;

class SyncController
{
    private $betModel;
    private $geminiService;
    private $betfairService;

    public function __construct()
    {
        set_time_limit(300);
        ignore_user_abort(true);

        $this->betModel = new Bet();
        $this->geminiService = new GeminiService();
        $this->betfairService = new BetfairService();
    }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    private function handleException(\Throwable $e)
    {
        echo json_encode([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

    /**
     * CRON LIVE MULTI-SPORT - Ogni minuto
     * Basato interamente su Betfair API
     */
    public function syncLive()
    {
        $this->sendJsonHeader();
        $results = ['scanned' => 0, 'analyzed' => 0, 'bets_placed' => 0];

        try {
            if (!$this->betfairService->isConfigured()) {
                echo "Betfair non configurato.\n";
                return;
            }

            // 1. Discover all Sports (Event Types)
            $sports = $this->betfairService->getEventTypes();
            if (empty($sports['result'])) throw new \Exception("Errore recupero sport Betfair");

            $allMarketIds = [];
            $marketToEventMap = [];

            foreach ($sports['result'] as $sport) {
                $sportId = $sport['eventType']['id'];
                $sportName = $sport['eventType']['name'];

                $events = $this->betfairService->getLiveEvents([$sportId]);
                if (empty($events['result'])) continue;

                $eventIds = array_map(fn($e) => $e['event']['id'], $events['result']);

                $catalogues = $this->betfairService->getMarketCatalogues($eventIds, 100);
                if (empty($catalogues['result'])) continue;

                foreach ($catalogues['result'] as $cat) {
                    $mId = $cat['marketId'];
                    $allMarketIds[] = $mId;
                    $marketToEventMap[$mId] = [
                        'sport' => $sportName,
                        'sportId' => $sportId,
                        'event' => $cat['event'],
                        'marketName' => $cat['marketName'],
                        'runners' => $cat['runners']
                    ];
                }
            }

            // 4. Batch Fetch Prices (Market Books)
            $betfairAggregated = [];
            $chunks = array_chunk($allMarketIds, 40);

            $candidates = [];
            foreach ($chunks as $chunk) {
                $books = $this->betfairService->getMarketBooks($chunk);
                if (!empty($books['result'])) {
                    foreach ($books['result'] as $book) {
                        $mId = $book['marketId'];
                        $meta = $marketToEventMap[$mId];

                        $eventData = [
                            'marketId' => $mId,
                            'sport' => $meta['sport'],
                            'event' => $meta['event']['name'],
                            'market' => $meta['marketName'],
                            'totalMatched' => $book['totalMatched'],
                            'runners' => array_map(function($r) use ($meta) {
                                $m = array_filter($meta['runners'], fn($rm) => $rm['selectionId'] === $r['selectionId']);
                                $name = reset($m)['runnerName'] ?? 'Unknown';
                                return [
                                    'name' => $name,
                                    'back' => $r['ex']['availableToBack'][0]['price'] ?? null,
                                    'lay' => $r['ex']['availableToLay'][0]['price'] ?? null
                                ];
                            }, $book['runners'])
                        ];

                        $betfairAggregated[] = array_merge($eventData, ['sportId' => $meta['sportId'], 'runners' => $meta['runners'], 'prices' => $book['runners'], 'status' => $book['status']]);
                        $results['scanned']++;

                        // Aggiungi ai candidati per Gemini se ha volume minimo (>100€) e mercato principale
                        if ($book['totalMatched'] > 100 && (stripos($meta['marketName'], 'Match Odds') !== false || count($meta['runners']) <= 3)) {
                             if (!$this->betModel->hasBet($mId)) {
                                 $candidates[] = $eventData;
                             }
                        }
                    }
                }
                usleep(50000);
            }

            // Salva cache per Dashboard
            file_put_contents(Config::DATA_PATH . 'betfair_live.json', json_encode(['response' => $betfairAggregated, 'timestamp' => time()]));

            // 5. Throttling: 1 analisi al minuto
            $cooldownFile = Config::DATA_PATH . 'gemini_cooldown.txt';
            $lastRun = file_exists($cooldownFile) ? (int)file_get_contents($cooldownFile) : 0;

            if (time() - $lastRun >= 60 && !empty($candidates)) {
                file_put_contents($cooldownFile, time());
                $this->executeGlobalAnalysis($candidates, $results);
            } else {
                echo "Gemini in cooldown o nessun candidato valido.\n";
            }

            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    private function executeGlobalAnalysis(array $candidates, &$results)
    {
        $funds = $this->betfairService->getFunds();
        $balance = [
            'available_balance' => $funds['availableToBetBalance'] ?? 0,
            'current_portfolio' => ($funds['availableToBetBalance'] ?? 0) + abs($funds['exposure'] ?? 0)
        ];

        if ($balance['available_balance'] < Config::MIN_BETFAIR_STAKE) return;

        // Limita a 20 candidati più liquidi per non saturare il prompt
        usort($candidates, fn($a, $b) => $b['totalMatched'] <=> $a['totalMatched']);
        $topCandidates = array_slice($candidates, 0, 20);

        $prediction = $this->geminiService->analyze($topCandidates, $balance);
        $results['analyzed']++;

        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $prediction, $matches)) {
            $betData = json_decode($matches[1], true);
            if ($betData && !empty($betData['marketId']) && !empty($betData['advice'])) {

                // Recupera metadati completi del mercato scelto
                $cacheFile = Config::DATA_PATH . 'betfair_live.json';
                $fullData = json_decode(file_get_contents($cacheFile), true);
                $event = null;
                foreach ($fullData['response'] as $m) {
                    if ($m['marketId'] === $betData['marketId']) { $event = $m; break; }
                }

                if ($event) {
                    $selectionId = $this->betfairService->mapAdviceToSelection($betData['advice'], $event['runners']);
                    if ($selectionId) {
                        $bfRes = $this->betfairService->placeBet($event['marketId'], $selectionId, $betData['odds'], $betData['stake']);
                        $res = isset($bfRes['status']) ? $bfRes : ($bfRes['result'] ?? null);

                        if ($res && isset($res['status']) && $res['status'] === 'SUCCESS') {
                             $results['bets_placed']++;
                             $this->betModel->create([
                                 'fixture_id' => $event['marketId'],
                                 'match_name' => $event['event']['name'],
                                 'advice' => $betData['advice'],
                                 'market' => $event['marketName'],
                                 'odds' => $betData['odds'],
                                 'stake' => $betData['stake'],
                                 'betfair_id' => $res['instructionReports'][0]['betId'] ?? null,
                                 'status' => 'placed'
                             ]);
                             echo "SCOMMESSA PIAZZATA: " . $event['event']['name'] . " - " . $betData['advice'] . "\n";
                        }
                    }
                }
            }
        }
    }

    public function syncHourly()
    {
        $this->sendJsonHeader();
        try {
            $this->betModel->cleanup();
            $this->betModel->deduplicate();
            echo json_encode(['status' => 'maintenance_ok']);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    public function sync() { $this->syncLive(); }
}
