<?php
// app/Dio/Controllers/DioQuantumController.php

namespace App\Dio\Controllers;

use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Dio\DioDatabase;
use PDO;

class DioQuantumController
{
    private $bf;
    private $gemini;
    private $db;

    public function __construct()
    {
        $this->bf = new BetfairService();
        $this->gemini = new GeminiService();
        $this->db = DioDatabase::getInstance()->getConnection();
    }

    public function index()
    {
        $pageTitle = 'Scommetto.AI - Dio Quantum';
        $stats = $this->getStats();
        $recentBets = $this->getRecentBets();
        $recentLogs = $this->getRecentLogs();
        $recentExperiences = $this->getRecentExperiences();
        $performanceHistory = $this->getPerformanceHistory();

        // Fetch live sports dynamically
        $eventTypesRes = $this->bf->getEventTypes(['inPlayOnly' => true]);
        $eventTypes = $eventTypesRes['result'] ?? [];

        // Fetch active events from the pending bets to filter the display
        $pendingBetEvents = array_filter($recentBets, fn($b) => $b['status'] === 'pending');
        $activeMarketIds = array_column($pendingBetEvents, 'market_id');

        // LOAD LIVE SCORES CACHE
        $liveScoresCache = [];
        if (file_exists(\App\Config\Config::DATA_PATH . 'live_scores.json')) {
            $liveScoresCache = json_decode(file_get_contents(\App\Config\Config::DATA_PATH . 'live_scores.json'), true) ?? [];
        }

        $liveSportsData = [];
        foreach ($eventTypes as $et) {
            $name = $et['eventType']['name'];
            $id = $et['eventType']['id'];

            $res = $this->bf->getLiveEvents([$id]);
            $events = $res['result'] ?? [];

            if (!empty($events)) {
                $liveSportsData[$name] = [
                    'id' => $id,
                    'events' => $events
                ];
            }
        }

        // INJECT SCORES
        foreach ($liveSportsData as $sport => &$data) {
            foreach ($data['events'] as &$eventObj) {
                $eventName = $eventObj['event']['name'];
                if (isset($liveScoresCache[$eventName])) {
                    $eventObj['score'] = $liveScoresCache[$eventName];
                }
            }
        }
        unset($data, $eventObj); // Break references

        require __DIR__ . '/../Views/dashboard.php';
    }

    public function scanAndTrade()
    {
        header('Content-Type: application/json');
        try {
            // 1. Get ALL active sport types with live events
            $eventTypesRes = $this->bf->getEventTypes(['inPlayOnly' => true]);
            $eventTypes = $eventTypesRes['result'] ?? [];

            $allOpportunities = [];

            foreach ($eventTypes as $sportType) {
                $sportId = $sportType['eventType']['id'];
                $sportName = $sportType['eventType']['name'];

                // 2. Get Live Events for this sport
                $liveEventsRes = $this->bf->getLiveEvents([$sportId]);
                $events = $liveEventsRes['result'] ?? [];

                if (empty($events))
                    continue;

                $eventIds = array_map(fn($e) => $e['event']['id'], $events);

                // 3. Get Market Catalogues (Removing filters to allow ALL markets: Over/Under, Handicap, etc.)
                $cataloguesRes = $this->bf->getMarketCatalogues($eventIds, 15);
                $catalogues = $cataloguesRes['result'] ?? [];

                if (empty($catalogues))
                    continue;

                $marketIds = array_map(fn($m) => $m['marketId'], $catalogues);

                // 4. Get Market Books (Prices & Liquidity)
                $booksRes = $this->bf->getMarketBooks($marketIds);
                $books = $booksRes['result'] ?? [];

                foreach ($books as $book) {
                    // Filter for high liquidity (> 5000€ matched total)
                    // This ensures we only trade on serious markets regardless of type
                    if (($book['totalMatched'] ?? 0) < 5000)
                        continue;

                    // Find the matching catalogue for metadata
                    $mc = array_filter($catalogues, fn($c) => $c['marketId'] === $book['marketId']);
                    $mc = reset($mc);

                    // Normalize data into "Ticker" format
                    $ticker = $this->normalizeMarketData($book, $mc, $sportName);

                    // 5. AI Analysis (Quantum Mode)
                    $analysis = $this->analyzeQuantum($ticker);

                    // CACHE LIVE SCORES FOR DASHBOARD
                    // We collect scores of ALL scanned high-liquidity matches
                    if (!empty($ticker['score'])) {
                        $liveScores[$ticker['event']] = $ticker['score'];
                    }

                    if ($analysis) {
                        $confidence = $analysis['confidence'] ?? 0;
                        $advice = $analysis['advice'] ?? '';
                        $action = ($confidence >= 85 && stripos($advice, 'PASS') === false) ? 'bet' : 'pass';

                        // Log thinking process
                        $this->logActivity($ticker, $analysis, $action);

                        if ($action === 'bet') {
                            $this->placeVirtualBet($ticker, $analysis);
                            $allOpportunities[] = [
                                'event' => $ticker['event'],
                                'market' => $ticker['marketName'],
                                'advice' => $analysis['advice'],
                                'odds' => $analysis['odds'],
                                'confidence' => $confidence
                            ];
                        }
                    }
                }
            }

            // SAVE SCORES CACHE
            file_put_contents(\App\Config\Config::DATA_PATH . 'live_scores.json', json_encode($liveScores));

            echo json_encode(['status' => 'success', 'scanned' => count($allOpportunities), 'opportunities' => $allOpportunities]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function normalizeMarketData($book, $mc, $sportName)
    {
        $runners = [];
        $totalBack = 0;
        foreach ($book['runners'] as $runner) {
            // Find runner name from catalogue
            $runnerName = 'Unknown';
            foreach ($mc['runners'] ?? [] as $rDesc) {
                if ($rDesc['selectionId'] == $runner['selectionId']) {
                    $runnerName = $rDesc['runnerName'];
                    break;
                }
            }

            $back = $runner['ex']['availableToBack'][0]['price'] ?? 0;
            $lay = $runner['ex']['availableToLay'][0]['price'] ?? 0;
            $runners[] = [
                'selectionId' => $runner['selectionId'],
                'name' => $runnerName,
                'back' => $back,
                'lay' => $lay,
                'lastPriceTraded' => $runner['lastPriceTraded'] ?? 0
            ];
            $totalBack += ($back > 0) ? (1 / $back) : 0;
        }

        // Dynamic Score Extraction (Betfair Native)
        $score = null;
        $scoreData = $book['marketDefinition']['score'] ?? null;
        if ($scoreData) {
            if (isset($scoreData['home']['score']) && isset($scoreData['away']['score'])) {
                $score = $scoreData['home']['score'] . " - " . $scoreData['away']['score'];
                // Tennis often has games/sets nested
                if (isset($scoreData['home']['games']) && $sportName === 'Tennis') {
                    $score .= " (Games: " . $scoreData['home']['games'] . "-" . $scoreData['away']['games'] . ")";
                }
            }
        }

        return [
            'marketId' => $book['marketId'],
            'marketName' => $mc['marketName'] ?? 'Unknown',
            'event' => $mc['event']['name'] ?? 'Unknown',
            'sport' => $sportName,
            'totalMatched' => $book['totalMatched'] ?? 0,
            'inPlay' => $book['inplay'] ?? false,
            'status' => $book['status'] ?? 'OPEN',
            'runners' => $runners,
            'score' => $score,
            'marketEfficency' => round($totalBack * 100, 2) . "%", // Overround
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function analyzeQuantum($ticker)
    {
        $balance = $this->getVirtualBalance();

        // RAG BRAIN: Retrieve past experiences for this sport/market
        $stmt = $this->db->prepare("SELECT outcome, lesson FROM experiences WHERE sport = ? ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$ticker['sport']]);
        $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ragContext = "";
        if (!empty($experiences)) {
            $ragContext = "MEMORIA RAG (Lezioni Apprese):\n";
            foreach ($experiences as $exp) {
                $status = ($exp['outcome'] === 'won') ? "✅ SUCCESSO" : "❌ FALLIMENTO";
                $ragContext .= "- $status: {$exp['lesson']}\n";
            }
            $ragContext .= "\n";
        }

        $prompt = "Sei un QUANT TRADER denominato 'Dio'. Non sei uno scommettitore, sei un analista di Price Action (Tape Reading).\n\n" .
            "MEMORIA RAG (Lezioni Precedenti):\n" . $ragContext . "\n" .
            "SITUAZIONE PORTAFOGLIO: Bankroll " . number_format($balance, 2) . "€\n\n" .
            "DATI ASSET (TICKER):\n" . json_encode($ticker, JSON_PRETTY_PRINT) . "\n\n" .
            "IL TUO VANTAGGIO (Price Action Rules):\n" .
            "1. Analizza 'lastPriceTraded' vs Quota Attuale: Se divergono, identifica il momentum.\n" .
            "2. Analizza lo SCORE vs QUOTE: Se un tennista è avanti di un break ma la quota non crolla, il mercato è scettico. Identifica il perché.\n" .
            "3. VOLUMI: Un volume alto indica precisione. Se il volume è basso, sii estremamente prudente.\n" .
            "4. IGNORA i nomi delle squadre/atleti. Guarda solo l'efficienza del mercato.\n\n" .
            "STRATEGIA OPERATIVA:\n" .
            "- Quota minima: 1.10.\n" .
            "- Stake: Usa Kelly Criterion prudente. Min 2€, Max " . ($balance * 0.05) . "€ (5%).\n" .
            "- Decisione: BACK, LAY o PASS.\n" .
            "- Confidence >= 85 per operare.\n\n" .
            "RISPONDI IN JSON:\n" .
            "{\n" .
            "  \"advice\": \"Runner Name\",\n" .
            "  \"selectionId\": \"ID\",\n" .
            "  \"odds\": 1.80,\n" .
            "  \"stake\": 2.0,\n" .
            "  \"confidence\": 90,\n" .
            "  \"motivation\": \"Analisi tecnica del nastro (es. Trend ribassista sulla favorita nonostante lo 0-0).\"\n" .
            "}";

        $response = $this->gemini->analyzeCustom($prompt);

        if (preg_match('/```json\s*([\s\S]*?)(?:```|$)/', $response, $matches)) {
            return json_decode(trim($matches[1]), true);
        }

        return json_decode($response, true);
    }

    private function placeVirtualBet($ticker, $analysis)
    {
        $stmt = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, motivation, score, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'virtual')");
        $stmt->execute([
            $ticker['marketId'],
            $ticker['marketName'],
            $ticker['event'],
            $ticker['sport'],
            $analysis['selectionId'] ?? '',
            $analysis['advice'] ?? 'Unknown',
            $analysis['odds'] ?? 0,
            $analysis['stake'] ?? 0,
            "Quantum Algo: " . ($analysis['motivation'] ?? ''),
            $ticker['score'] ?? null
        ]);

        // Update temporary balance (to avoid over-stressing the bankroll in one scan cycle)
        $newBalance = $this->getVirtualBalance() - ($analysis['stake'] ?? 0);
        $this->updateVirtualBalance($newBalance);
    }

    private function getVirtualBalance()
    {
        $stmt = $this->db->prepare("SELECT value FROM system_state WHERE key = 'virtual_balance'");
        $stmt->execute();
        return (float) ($stmt->fetchColumn() ?: 100.00);
    }

    private function updateVirtualBalance($balance)
    {
        $stmt = $this->db->prepare("UPDATE system_state SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'virtual_balance'");
        $stmt->execute([number_format($balance, 2, '.', '')]);
    }

    private function getStats()
    {
        $balance = $this->getVirtualBalance();

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bets");
        $stmt->execute();
        $totalBets = $stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT SUM(profit) FROM bets WHERE status != 'pending'");
        $stmt->execute();
        $totalProfit = (float) $stmt->fetchColumn();

        return [
            'balance' => $balance,
            'total_bets' => $totalBets,
            'total_profit' => $totalProfit,
            'roi' => $totalBets > 0 ? ($totalProfit / ($totalBets * 2)) * 100 : 0 // Simplified ROI calculation
        ];
    }

    private function getRecentBets()
    {
        $stmt = $this->db->prepare("SELECT * FROM bets WHERE runner_name NOT LIKE '%PASS%' ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logActivity($ticker, $analysis, $action)
    {
        if ($action === 'pass')
            return;

        $stmt = $this->db->prepare("INSERT INTO logs (event_name, market_name, selection_name, confidence, action, motivation) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ticker['event'],
            $ticker['marketName'],
            $analysis['advice'] ?? 'N/A',
            $analysis['confidence'] ?? 0,
            $action,
            $analysis['motivation'] ?? ''
        ]);
    }

    private function getRecentLogs()
    {
        $stmt = $this->db->prepare("SELECT * FROM logs WHERE action = 'bet' ORDER BY created_at DESC LIMIT 20");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentExperiences()
    {
        $stmt = $this->db->prepare("SELECT * FROM experiences ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPerformanceHistory()
    {
        // Get settled bets ordered by date to calculate cumulative profit
        $stmt = $this->db->prepare("SELECT profit, settled_at FROM bets WHERE status IN ('won', 'lost') ORDER BY settled_at ASC");
        $stmt->execute();
        $settled = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = [];
        $cumulative = 100.0; // Initial bankroll

        // Initial point
        $history[] = ['t' => 'Start', 'v' => 100.0];

        foreach ($settled as $s) {
            $cumulative += (float) $s['profit'];
            $history[] = [
                't' => date('d/m H:i', strtotime($s['settled_at'])),
                'v' => round($cumulative, 2)
            ];
        }

        return $history;
    }
}
