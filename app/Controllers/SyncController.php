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
    private $analysisModel;
    private $geminiService;
    private $betfairService;

    public function __construct()
    {
        set_time_limit(300);
        ignore_user_abort(true);

        $this->betModel = new Bet();
        $this->analysisModel = new Analysis();
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

            foreach ($chunks as $chunk) {
                $books = $this->betfairService->getMarketBooks($chunk);
                if (!empty($books['result'])) {
                    foreach ($books['result'] as $book) {
                        $mId = $book['marketId'];
                        $meta = $marketToEventMap[$mId];

                        $eventData = [
                            'marketId' => $mId,
                            'sport' => $meta['sport'],
                            'sportId' => $meta['sportId'],
                            'event' => $meta['event'],
                            'marketName' => $meta['marketName'],
                            'runners' => $meta['runners'],
                            'status' => $book['status'],
                            'inplay' => $book['inplay'],
                            'totalMatched' => $book['totalMatched'],
                            'prices' => $book['runners']
                        ];

                        $betfairAggregated[] = $eventData;
                        $results['scanned']++;

                        if (stripos($meta['marketName'], 'Match Odds') !== false || count($meta['runners']) <= 3) {
                             $this->analyzeBetfairEvent($eventData, $results);
                        }
                    }
                }
                usleep(100000);
            }

            file_put_contents(Config::DATA_PATH . 'betfair_live.json', json_encode(['response' => $betfairAggregated, 'timestamp' => time()]));
            echo json_encode($results);
        } catch (\Throwable $e) { $this->handleException($e); }
    }

    private function analyzeBetfairEvent($event, &$results)
    {
        if ($this->betModel->hasBet($event['marketId'])) return;

        $funds = $this->betfairService->getFunds();
        $balance = [
            'available_balance' => $funds['availableToBetBalance'] ?? 0,
            'current_portfolio' => ($funds['availableToBetBalance'] ?? 0) + abs($funds['exposure'] ?? 0)
        ];

        if ($balance['available_balance'] < Config::MIN_BETFAIR_STAKE) return;

        $prediction = $this->geminiService->analyze($event, $balance);
        $results['analyzed']++;

        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $prediction, $matches)) {
            $betData = json_decode($matches[1], true);
            if ($betData && !empty($betData['advice']) && $betData['stake'] >= Config::MIN_BETFAIR_STAKE) {

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
