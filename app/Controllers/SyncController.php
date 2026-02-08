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
            if (empty($sports['result']))
                throw new \Exception("Errore recupero sport Betfair");

            $allMarketIds = [];
            $marketToEventMap = [];

            foreach ($sports['result'] as $sport) {
                $sportId = $sport['eventType']['id'];
                $sportName = $sport['eventType']['name'];

                $events = $this->betfairService->getLiveEvents([$sportId]);
                if (empty($events['result']))
                    continue;

                $eventIds = array_map(fn($e) => $e['event']['id'], $events['result']);

                $catalogues = $this->betfairService->getMarketCatalogues($eventIds, 100);
                if (empty($catalogues['result']))
                    continue;

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
                            'runners' => array_map(function ($r) use ($meta) {
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
            $lastRun = file_exists($cooldownFile) ? (int) file_get_contents($cooldownFile) : 0;

            if (time() - $lastRun >= 60 && !empty($candidates)) {
                file_put_contents($cooldownFile, time());
                $this->executeGlobalAnalysis($candidates, $results);
            } else {
                echo "Gemini in cooldown o nessun candidato valido.\n";
            }

            echo json_encode($results);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function executeGlobalAnalysis(array $candidates, &$results)
    {
        $funds = $this->betfairService->getFunds();
        $balance = [
            'available_balance' => $funds['availableToBetBalance'] ?? 0,
            'current_portfolio' => ($funds['availableToBetBalance'] ?? 0) + abs($funds['exposure'] ?? 0)
        ];

        if ($balance['available_balance'] < Config::MIN_BETFAIR_STAKE && !Config::isSimulationMode())
            return;

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
                    if ($m['marketId'] === $betData['marketId']) {
                        $event = $m;
                        break;
                    }
                }

                if ($event) {
                    // Refetch the specific market to get the freshest price before betting
                    $freshBook = $this->betfairService->getMarketBooks([$event['marketId']]);
                    $finalPrice = $betData['odds'];

                    if (!empty($freshBook['result'][0]['runners'])) {
                        foreach ($freshBook['result'][0]['runners'] as $fr) {
                            $selectionId = $this->betfairService->mapAdviceToSelection($betData['advice'], $event['runners']);
                            if ($fr['selectionId'] == $selectionId) {
                                // Use the best available back price if it moved in our favor or slightly against
                                $bestBack = $fr['ex']['availableToBack'][0]['price'] ?? null;
                                if ($bestBack)
                                    $finalPrice = $bestBack;
                                break;
                            }
                        }
                    }

                    $selectionId = $this->betfairService->mapAdviceToSelection($betData['advice'], $event['runners']);
                    if ($selectionId) {
                        // CRITICAL: Double check if we already have a bet on this market
                        if ($this->betModel->hasBet($event['marketId'])) {
                            echo "SKIP: Bet already exists for market " . $event['marketId'] . "\n";
                            return; // Exit function since we only process one bet per call
                        }

                        $isSimulation = Config::isSimulationMode();
                        $status = 'pending';
                        $betfairId = null;
                        $note = '';

                        if ($isSimulation) {
                            $status = 'placed';
                            $note = '[SIMULAZIONE] Bet Virtuale';
                            $this->betModel->create([
                                'fixture_id' => $event['marketId'],
                                'match_name' => $event['event']['name'],
                                'advice' => $betData['advice'],
                                'market' => $event['marketName'],
                                'odds' => $finalPrice,
                                'stake' => $betData['stake'],
                                'status' => $status,
                                'notes' => $note,
                                'bookmaker_name' => 'Betfair.it'
                            ]);
                            echo "SCOMMESSA SIMULATA: " . $event['event']['name'] . " - " . $betData['advice'] . " @ $finalPrice\n";
                            $results['bets_placed']++;
                        } else {
                            $bfRes = $this->betfairService->placeBet($event['marketId'], $selectionId, $finalPrice, $betData['stake']);
                            $res = isset($bfRes['status']) ? $bfRes : ($bfRes['result'] ?? null);

                            if ($res && isset($res['status']) && $res['status'] === 'SUCCESS') {
                                $results['bets_placed']++;
                                $this->betModel->create([
                                    'fixture_id' => $event['marketId'],
                                    'match_name' => $event['event']['name'],
                                    'advice' => $betData['advice'],
                                    'market' => $event['marketName'],
                                    'odds' => $finalPrice,
                                    'stake' => $betData['stake'],
                                    'betfair_id' => $res['instructionReports'][0]['betId'] ?? null,
                                    'status' => 'placed',
                                    'bookmaker_name' => 'Betfair.it'
                                ]);
                                echo "SCOMMESSA PIAZZATA: " . $event['event']['name'] . " - " . $betData['advice'] . " @ $finalPrice\n";
                            }
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

            // 1. Settle ALL sports from Betfair Real Orders
            $realSettled = $this->settleBetfairBets();

            // 2. Sincronizza eventi futuri Betfair
            $this->syncUpcoming();

            echo json_encode(['status' => 'maintenance_ok', 'real_settled' => $realSettled, 'upcoming_synced' => true]);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function syncUpcoming()
    {
        if (!$this->betfairService->isConfigured())
            return;

        try {
            $sports = $this->betfairService->getEventTypes();
            if (empty($sports['result']))
                return;

            $upcomingEvents = [];
            $allMarketIds = [];
            $marketToEventMap = [];

            $now = new \DateTime();
            $end = (new \DateTime())->add(new \DateInterval('P2D')); // Prossime 48 ore

            foreach ($sports['result'] as $sport) {
                $sportId = $sport['eventType']['id'];
                $sportName = $sport['eventType']['name'];

                // Recupera eventi per le prossime 48 ore
                $events = $this->betfairService->request('listEvents', [
                    'filter' => [
                        'eventTypeIds' => [$sportId],
                        'marketStartTime' => [
                            'from' => $now->format('Y-m-d\TH:i:s\Z'),
                            'to' => $end->format('Y-m-d\TH:i:s\Z')
                        ]
                    ]
                ]);

                if (empty($events['result']))
                    continue;

                $eventIds = array_map(fn($e) => $e['event']['id'], $events['result']);

                // Recupera cataloghi mercati (es. Match Odds)
                $catalogues = $this->betfairService->request('listMarketCatalogue', [
                    'filter' => [
                        'eventIds' => $eventIds,
                        'marketBettingTypes' => ['ODDS']
                    ],
                    'maxResults' => 200,
                    'marketProjection' => ['RUNNER_DESCRIPTION', 'MARKET_DESCRIPTION', 'EVENT', 'COMPETITION']
                ]);

                if (empty($catalogues['result']))
                    continue;

                foreach ($catalogues['result'] as $cat) {
                    // Filtriamo per mercati principali o con volume
                    $mName = $cat['marketName'];
                    if (stripos($mName, 'Match Odds') === false && stripos($mName, 'Esito Finale') === false && count($cat['runners']) > 3)
                        continue;

                    $mId = $cat['marketId'];
                    $allMarketIds[] = $mId;
                    $marketToEventMap[$mId] = [
                        'sport' => $sportName,
                        'sportId' => $sportId,
                        'event' => $cat['event'],
                        'marketName' => $cat['marketName'],
                        'competition' => $cat['competition'] ?? null,
                        'runners' => $cat['runners']
                    ];
                }
            }

            // Recupera prezzi in batch per upcoming
            $chunks = array_chunk($allMarketIds, 40);
            foreach ($chunks as $chunk) {
                $books = $this->betfairService->getMarketBooks($chunk);
                if (!empty($books['result'])) {
                    foreach ($books['result'] as $book) {
                        $mId = $book['marketId'];
                        $meta = $marketToEventMap[$mId];

                        $upcomingEvents[] = [
                            'marketId' => $mId,
                            'sport' => $meta['sport'],
                            'sportId' => $meta['sportId'],
                            'event' => $meta['event'],
                            'competition' => $meta['competition'],
                            'marketName' => $meta['marketName'],
                            'totalMatched' => $book['totalMatched'],
                            'runners' => array_map(function ($r) use ($meta) {
                                $m = array_filter($meta['runners'], fn($rm) => $rm['selectionId'] === $r['selectionId']);
                                $name = reset($m)['runnerName'] ?? 'Unknown';
                                return [
                                    'name' => $name,
                                    'selectionId' => $r['selectionId'],
                                    'back' => $r['ex']['availableToBack'][0]['price'] ?? null,
                                    'lay' => $r['ex']['availableToLay'][0]['price'] ?? null
                                ];
                            }, $book['runners']),
                            'startTime' => $meta['event']['openDate'] ?? null
                        ];
                    }
                }
                usleep(50000);
            }

            file_put_contents(Config::DATA_PATH . 'betfair_upcoming.json', json_encode(['response' => $upcomingEvents, 'timestamp' => time()]));

            // Analisi Gemini per Hot Predictions (solo se ci sono candidati validi)
            if (!empty($upcomingEvents)) {
                $this->executeUpcomingAnalysis($upcomingEvents);
            }

        } catch (\Throwable $e) {
            error_log("Sync Upcoming Error: " . $e->getMessage());
        }
    }

    private function executeUpcomingAnalysis(array $upcoming)
    {
        try {
            // Prendiamo i top 15 eventi per volume
            usort($upcoming, fn($a, $b) => $b['totalMatched'] <=> $a['totalMatched']);
            $candidates = array_slice($upcoming, 0, 15);

            $prediction = $this->geminiService->analyze($candidates, ['is_upcoming' => true]);

            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $prediction, $matches)) {
                $data = json_decode($matches[1], true);
                if ($data) {
                    file_put_contents(Config::DATA_PATH . 'betfair_hot_predictions.json', json_encode(['response' => $data, 'timestamp' => time()]));
                }
            }
        } catch (\Throwable $e) {
            error_log("Upcoming Analysis Error: " . $e->getMessage());
        }
    }

    private function settleBetfairBets()
    {
        if (!$this->betfairService->isConfigured())
            return 0;

        $cleared = $this->betfairService->getClearedOrders();
        if (empty($cleared['clearedOrders']))
            return 0;

        $count = 0;
        $db = Database::getInstance()->getConnection();

        foreach ($cleared['clearedOrders'] as $order) {
            $betId = $order['betId'];
            $status = ($order['lastMatchedPrice'] > 0 && $order['profit'] > 0) ? 'won' : (($order['profit'] < 0) ? 'lost' : 'void');

            // Update local DB status based on real betfair outcome
            $stmt = $db->prepare("UPDATE bets SET status = ?, result = ? WHERE betfair_id = ? AND status = 'placed'");
            $stmt->execute([$status, $status === 'won' ? 'WIN' : 'LOSS', $betId]);
            if ($stmt->rowCount() > 0)
                $count++;
        }
        return $count;
    }

    public function sync()
    {
        $this->syncLive();
    }

    public function getUsage()
    {
        $this->sendJsonHeader();
        echo json_encode([
            'requests_limit' => 0,
            'requests_used' => 0,
            'requests_remaining' => 0,
            'note' => 'API-Football usage tracking disabled'
        ]);
    }

    public function deepSync($leagueId, $season)
    {
        $this->sendJsonHeader();
        echo json_encode(['status' => 'disabled', 'message' => 'API-Football deep sync is disabled.']);
    }
}
