<?php
// app/GiaNik/Controllers/GiaNikController.php

namespace App\GiaNik\Controllers;

use App\Config\Config;
use App\Services\BetfairService;
use App\Services\GeminiService;
use App\GiaNik\GiaNikDatabase;
use PDO;

class GiaNikController
{
    private $bf;
    private $db;

    public function __construct()
    {
        $this->bf = new BetfairService();
        $this->db = GiaNikDatabase::getInstance()->getConnection();
    }

    public function index()
    {
        require __DIR__ . '/../Views/gianik_live_page.php';
    }

    public function live()
    {
        try {
            if (!$this->bf->isConfigured()) {
                echo '<div class="text-warning p-4">Betfair non configurato.</div>';
                return;
            }

            // 1. Get Event Types (Sports)
            $eventTypesRes = $this->bf->getEventTypes();
            $eventTypeIds = [];
            foreach ($eventTypesRes['result'] ?? [] as $et) {
                $eventTypeIds[] = $et['eventType']['id'];
            }

            // 2. Get Live Events for all sports
            $liveEventsRes = $this->bf->getLiveEvents($eventTypeIds);
            $events = $liveEventsRes['result'] ?? [];

            if (empty($events)) {
                echo '<div class="text-center py-10"><span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live su Betfair</span></div>';
                return;
            }

            $eventIds = array_map(fn($e) => $e['event']['id'], $events);

            // 3. Get Market Catalogues for these events (Match Odds)
            // We'll chunk the event IDs to avoid too large requests if needed, but 50 is usually fine.
            $marketCatalogues = [];
            $chunks = array_chunk($eventIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketCatalogues($chunk, 100, ['MATCH_ODDS', 'WINNER', 'MONEYLINE']);
                if (isset($res['result'])) {
                    $marketCatalogues = array_merge($marketCatalogues, $res['result']);
                }
            }

            // 4. Get Market Books (Prices)
            $marketIds = array_map(fn($m) => $m['marketId'], $marketCatalogues);
            $marketBooks = [];
            $chunks = array_chunk($marketIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketBooks($chunk);
                if (isset($res['result'])) {
                    $marketBooks = array_merge($marketBooks, $res['result']);
                }
            }

            // 5. Merge Data
            $marketBooksMap = [];
            foreach ($marketBooks as $mb) {
                $marketBooksMap[$mb['marketId']] = $mb;
            }

            $groupedMatches = [];
            foreach ($marketCatalogues as $mc) {
                $marketId = $mc['marketId'];
                if (!isset($marketBooksMap[$marketId])) continue;

                $mb = $marketBooksMap[$marketId];
                $sport = $mc['eventType']['name'] ?? 'Altro';

                $m = [
                    'marketId' => $marketId,
                    'event' => $mc['event']['name'],
                    'event_id' => $mc['event']['id'],
                    'competition' => $mc['competition']['name'] ?? '',
                    'sport' => $sport,
                    'totalMatched' => $mb['totalMatched'] ?? 0,
                    'runners' => []
                ];

                // Merge runner names and prices
                $runnerNames = [];
                foreach ($mc['runners'] as $r) {
                    $runnerNames[$r['selectionId']] = $r['runnerName'];
                }

                foreach ($mb['runners'] as $r) {
                    $m['runners'][] = [
                        'selectionId' => $r['selectionId'],
                        'name' => $runnerNames[$r['selectionId']] ?? 'Unknown',
                        'back' => $r['ex']['availableToBack'][0]['price'] ?? '-'
                    ];
                }

                $groupedMatches[$sport][] = $m;
            }

            // Sort sports by count
            uksort($groupedMatches, function($a, $b) use ($groupedMatches) {
                return count($groupedMatches[$b]) <=> count($groupedMatches[$a]);
            });

            // Account Funds
            $account = ['available' => 0, 'exposure' => 0];
            $funds = $this->bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];
            $account['available'] = $funds['availableToBetBalance'] ?? 0;
            $account['exposure'] = abs($funds['exposure'] ?? 0);

            // Virtual Balance (from SQLite bets if needed, but let's just show a default for now)
            // or maybe we should have a settings table in SQLite too.
            $virtualBalance = 1000.00; // Default

            require __DIR__ . '/../Views/partials/gianik_live.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore GiaNik Live: ' . $e->getMessage() . '</div>';
        }
    }

    public function analyze($marketId)
    {
        try {
            // Get market data directly from Betfair for this specific market
            $res = $this->bf->getMarketBooks([$marketId]);
            $mb = $res['result'][0] ?? null;

            if (!$mb) {
                echo '<div class="glass p-10 rounded-3xl text-center border-danger/20 text-danger uppercase font-black italic">Evento non trovato.</div>';
                return;
            }

            // Get catalogue to have names
            $resCat = $this->bf->request('listMarketCatalogue', [
                'filter' => ['marketIds' => [$marketId]],
                'marketProjection' => ['RUNNER_DESCRIPTION', 'EVENT', 'COMPETITION', 'EVENT_TYPE']
            ]);
            $mc = $resCat['result'][0] ?? null;

            $event = [
                'marketId' => $marketId,
                'event' => $mc['event']['name'] ?? 'Unknown',
                'competition' => $mc['competition']['name'] ?? '',
                'sport' => $mc['eventType']['name'] ?? '',
                'totalMatched' => $mb['totalMatched'] ?? 0,
                'runners' => []
            ];

            $runnerNames = [];
            if ($mc) {
                foreach ($mc['runners'] as $r) {
                    $runnerNames[$r['selectionId']] = $r['runnerName'];
                }
            }

            foreach ($mb['runners'] as $r) {
                $event['runners'][] = [
                    'selectionId' => $r['selectionId'],
                    'name' => $runnerNames[$r['selectionId']] ?? 'Unknown',
                    'back' => $r['ex']['availableToBack'][0]['price'] ?? 0
                ];
            }

            $funds = $this->bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];

            $available = $funds['availableToBetBalance'] ?? 0;
            $exposure = abs($funds['exposure'] ?? 0);

            $balance = [
                'available_balance' => $available,
                'current_portfolio' => $available + $exposure
            ];

            $gemini = new GeminiService();
            // GiaNik option strictly uses candidates[0] and no external DB info
            $predictionRaw = $gemini->analyze([$event], array_merge($balance, ['is_gianik' => true]));

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

    public function placeBet()
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $marketId = $input['marketId'] ?? null;
            $selectionId = $input['selectionId'] ?? null;
            $odds = $input['odds'] ?? null;
            $stake = $input['stake'] ?? 2.0;
            $type = $input['type'] ?? 'virtual'; // 'virtual' or 'real'
            $eventName = $input['eventName'] ?? 'Unknown';
            $sport = $input['sport'] ?? 'Unknown';
            $runnerName = $input['runnerName'] ?? 'Unknown';
            $motivation = $input['motivation'] ?? '';

            if (!$marketId || !$selectionId || !$odds) {
                echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
                return;
            }

            if (!$selectionId && $runnerName) {
                // Try to find selectionId from advice/runnerName
                $resCat = $this->bf->request('listMarketCatalogue', [
                    'filter' => ['marketIds' => [$marketId]],
                    'marketProjection' => ['RUNNER_DESCRIPTION']
                ]);
                $runners = $resCat['result'][0]['runners'] ?? [];
                $selectionId = $this->bf->mapAdviceToSelection($runnerName, $runners);
            }

            if (!$selectionId) {
                echo json_encode(['status' => 'error', 'message' => 'Impossibile mappare la selezione']);
                return;
            }

            $betfairId = null;
            if ($type === 'real') {
                $res = $this->bf->placeBet($marketId, $selectionId, $odds, $stake);
                if (($res['status'] ?? '') === 'SUCCESS') {
                    $betfairId = $res['instructionReports'][0]['betId'] ?? null;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Errore Betfair: ' . ($res['errorCode'] ?? 'Unknown')]);
                    return;
                }
            }

            // Save to SQLite
            $stmt = $this->db->prepare("INSERT INTO bets (market_id, event_name, sport, selection_id, runner_name, odds, stake, type, betfair_id, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$marketId, $eventName, $sport, $selectionId, $runnerName, $odds, $stake, $type, $betfairId, $motivation]);

            echo json_encode(['status' => 'success', 'message' => 'Scommessa piazzata (' . $type . ')']);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
