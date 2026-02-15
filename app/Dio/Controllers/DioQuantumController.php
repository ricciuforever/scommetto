<?php
// app/Dio/Controllers/DioQuantumController.php

namespace App\Dio\Controllers;

use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Services\MoneyManagementService;
use App\Config\Config;
use App\Dio\DioDatabase;
use PDO;

class DioQuantumController
{
    private $bf;
    private $gemini;
    private $db;
    private $cachedPortfolio = null;

    public function __construct()
    {
        $this->db = DioDatabase::getInstance()->getConnection();

        // Fetch custom Betfair credentials from agent database
        $overrides = $this->db->query("SELECT key, value FROM system_state WHERE key LIKE 'BETFAIR_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $overrides = array_filter($overrides);

        $this->bf = new BetfairService($overrides, 'dio');
        $this->gemini = new GeminiService();
    }

    public function index()
    {
        $pageTitle = 'Scommetto.AI - Dio Quantum';

        $operationalMode = $this->db->query("SELECT value FROM system_state WHERE key = 'operational_mode'")->fetchColumn() ?: 'virtual';

        if ($operationalMode === 'real') {
            $this->syncWithBetfair();
        }

        $actualTotal = null;
        if ($operationalMode === 'real') {
            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            if (isset($funds['availableToBetBalance'])) {
                $actualTotal = (float)$funds['availableToBetBalance'] + abs((float)($funds['exposure'] ?? 0));
            }
        }

        $portfolio = $this->recalculatePortfolio($actualTotal);
        $stats = $portfolio;

        // Overlay real balance if available
        if ($operationalMode === 'real' && isset($funds['availableToBetBalance'])) {
            $stats['available_balance'] = (float)$funds['availableToBetBalance'];
            $stats['exposure_balance'] = abs((float)($funds['exposure'] ?? 0));
            $stats['total_balance'] = $stats['available_balance'] + $stats['exposure_balance'];
        }

        $recentBets = $this->getRecentBets($portfolio['placed_ids'] ?? null);
        $recentLogs = $this->getRecentLogs();
        $recentExperiences = $this->getRecentExperiences();
        $performanceHistory = $portfolio['history'];

        // Recupera timestamp ultima scansione AI
        $lastScan = $this->db->query("SELECT value FROM system_state WHERE key = 'last_quantum_scan'")->fetchColumn();

        // Synchronize virtual balance in DB (only if in virtual mode)
        if ($operationalMode === 'virtual') {
            $this->updateVirtualBalance($portfolio['available_balance']);
        }

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

        $currentMode = $operationalMode;
        require __DIR__ . '/../Views/dashboard.php';
    }

    public function scanAndTrade()
    {
        $this->sendJsonHeader();
        $liveScores = [];
        $targetSports = [1, 2, 5, 7522]; // Soccer, Tennis, Rugby Union, Basketball

        // CHECK IF AVAILABLE BALANCE >= 2€
        $portfolio = $this->recalculatePortfolio();

        // Synchronize virtual balance in DB (only if in virtual mode)
        $operationalMode = $this->db->query("SELECT value FROM system_state WHERE key = 'operational_mode'")->fetchColumn() ?: 'virtual';
        if ($operationalMode === 'virtual') {
            $this->updateVirtualBalance($portfolio['available_balance']);
        }

        if ($portfolio['available_balance'] < 2.00) {
            echo json_encode(['status' => 'success', 'message' => 'Dio Quantum fermo: Saldo insufficiente (< 2€)']);
            return;
        }

        // Throttling: 1 scan ogni 180 secondi per non saturare Betfair e Gemini
        $cooldownFile = \App\Config\Config::DATA_PATH . 'dio_quantum_cooldown.txt';
        $lastRun = file_exists($cooldownFile) ? (int) file_get_contents($cooldownFile) : 0;
        if (time() - $lastRun < 180) {
            echo json_encode(['status' => 'success', 'message' => 'Dio Quantum in cooldown']);
            return;
        }
        file_put_contents($cooldownFile, time());

        // Aggiorna timestamp ultima scansione nel database per monitoraggio dashboard (esplicito UTC)
        $nowUtc = gmdate('Y-m-d H:i:s');
        $this->db->prepare("INSERT OR REPLACE INTO system_state (key, value, updated_at) VALUES ('last_quantum_scan', ?, ?)")->execute([$nowUtc, $nowUtc]);

        try {
            // Fetch dynamic config for Dio
            $config = $this->db->query("SELECT key, value FROM system_state")->fetchAll(PDO::FETCH_KEY_PAIR);
            $strategyPrompt = $config['strategy_prompt'] ?? '';
            $stakeMode = $config['stake_mode'] ?? 'kelly';
            $stakeValue = (float)($config['stake_value'] ?? 0.10);
            $minConfidence = (int)($config['min_confidence'] ?? 75);
            $minStake = (float)($config['min_stake'] ?? 2.00);
            $operationalMode = $config['operational_mode'] ?? 'virtual';

            // 1. Get ALL active sport types with live events
            $eventTypesRes = $this->bf->getEventTypes(['inPlayOnly' => true]);
            $eventTypes = $eventTypesRes['result'] ?? [];

            $allOpportunities = [];
            $batchTickers = [];

            foreach ($eventTypes as $sportType) {
                $sportId = (int)$sportType['eventType']['id'];
                $sportName = $sportType['eventType']['name'];

                // Filter for requested sports
                if (!in_array($sportId, $targetSports)) continue;

                // 2. Get Live Events for this sport
                $liveEventsRes = $this->bf->getLiveEvents([$sportId]);
                $events = $liveEventsRes['result'] ?? [];

                if (empty($events))
                    continue;

                $eventIds = array_map(fn($e) => $e['event']['id'], $events);

                // 3. Get Market Catalogues
                $cataloguesRes = $this->bf->getMarketCatalogues($eventIds, 50);
                $catalogues = $cataloguesRes['result'] ?? [];

                if (empty($catalogues))
                    continue;

                $marketIds = array_map(fn($m) => $m['marketId'], $catalogues);

                // 4. Get Market Books (Prices & Liquidity)
                $booksRes = $this->bf->getMarketBooks($marketIds);
                $books = $booksRes['result'] ?? [];

                foreach ($books as $book) {
                    // Find the matching catalogue for metadata
                    $mc = array_filter($catalogues, fn($c) => $c['marketId'] === $book['marketId']);
                    $mc = reset($mc);

                    // Filter for OPEN markets only
                    if (($book['status'] ?? '') !== 'OPEN') {
                        continue;
                    }

                    // Avoid duplicate bets in Dio for the same match
                    if ($this->isMatchActiveInDio($mc['event']['name'] ?? '')) {
                        $this->logActivity(['event' => $mc['event']['name'] ?? 'Unknown', 'marketName' => $mc['marketName'] ?? 'Unknown'], ['motivation' => 'Match già attivo in Dio'], 'SKIP_ACTIVE');
                        continue;
                    }

                    // Filter for minimum liquidity to avoid wasting AI calls on irrelevant markets
                    $liquidity = (float)($book['totalMatched'] ?? 0);
                    if ($liquidity < 2000) {
                        if ($liquidity > 0) {
                            $this->logActivity(['event' => $mc['event']['name'] ?? 'Unknown', 'marketName' => $mc['marketName'] ?? 'Unknown'], ['motivation' => 'Liquidità insufficiente (< 2000€): ' . round($liquidity) . '€'], 'SKIP_LIQUIDITY');
                        }
                        continue;
                    }

                    // Normalize data into "Ticker" format
                    $ticker = $this->normalizeMarketData($book, $mc, $sportName);
                    $allOpportunities[] = $ticker; // Temporary collection for sorting

                    // CACHE LIVE SCORES FOR DASHBOARD
                    if (!empty($ticker['score'])) {
                        $liveScores[$ticker['event']] = $ticker['score'];
                    }
                }
            }

            if (!empty($allOpportunities)) {
                // Sort by liquidity (totalMatched) descending
                usort($allOpportunities, fn($a, $b) => ($b['totalMatched'] ?? 0) <=> ($a['totalMatched'] ?? 0));

                // Take top 30 for analysis
                $batchTickers = array_slice($allOpportunities, 0, 30);
                $allOpportunities = []; // Reset for recording actual opportunities later

                // 5. AI Analysis (Quantum Batch Mode - 1 call for up to 30 tickers)
                $batchResults = $this->analyzeQuantumBatch($batchTickers, $strategyPrompt, $minConfidence);

                $workingTotalBankroll = $portfolio['total_balance'];
                $workingAvailableBalance = $portfolio['available_balance'];

                foreach ($batchResults as $index => $analysis) {
                    $ticker = $batchTickers[$index] ?? null;
                    if (!$ticker || !$analysis) continue;

                    $confidence = (float)($analysis['confidence'] ?? 0);
                    $advice = $analysis['advice'] ?? '';

                    // Decidi l'azione base (Passa se confidence < soglia)
                    $action = ($confidence >= $minConfidence && stripos($advice, 'PASS') === false) ? 'bet' : 'pass';

                    if ($action === 'bet') {
                        // --- CALCOLO STAKE (Dinamico) ---
                        $decision = MoneyManagementService::calculateStake(
                            $workingTotalBankroll,
                            (float)$analysis['odds'],
                            $confidence,
                            $stakeMode,
                            $stakeValue,
                            $minStake
                        );

                        if (!$decision['is_value_bet'] || $decision['stake'] < max(2.00, $minStake)) {
                            $action = 'SKIP_MM';
                            $analysis['motivation'] .= " [SKIP MM: " . $decision['reason'] . "]";
                        } else {
                            $stake = $decision['stake'];
                            if ($stake > $workingAvailableBalance) {
                                $stake = $workingAvailableBalance;
                            }

                            if ($stake >= max(2.00, $minStake)) {
                                $analysis['stake'] = $stake;

                                $placed = false;
                                if ($operationalMode === 'real') {
                                    $placed = $this->placeRealBet($ticker, $analysis);
                                } else {
                                    $this->placeVirtualBet($ticker, $analysis);
                                    $placed = true;
                                }

                                if (!$placed) {
                                    $action = 'pass';
                                    continue;
                                }

                                $workingAvailableBalance -= $stake;
                                $workingTotalBankroll -= $stake;

                                $allOpportunities[] = [
                                    'event' => $ticker['event'],
                                    'market' => $ticker['marketName'],
                                    'advice' => $analysis['advice'],
                                    'odds' => $analysis['odds'],
                                    'confidence' => $confidence,
                                    'stake' => $stake
                                ];
                            } else {
                                $action = 'pass';
                            }
                        }
                    }

                    // Log thinking process
                    $this->logActivity($ticker, $analysis, $action);
                }
            }

            // SAVE SCORES CACHE
            file_put_contents(\App\Config\Config::DATA_PATH . 'live_scores.json', json_encode($liveScores));

            echo json_encode(['status' => 'success', 'scanned' => count($batchTickers), 'opportunities' => $allOpportunities]);
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

    private function analyzeQuantumBatch($tickers, $customPrompt = null, $minConfidence = 80)
    {
        $portfolio = $this->recalculatePortfolio();
        $totalBalance = $portfolio['total_balance'];
        $availableBalance = $portfolio['available_balance'];

        // RAG BRAIN: Retrieve past experiences for sports in this batch
        $sports = array_unique(array_column($tickers, 'sport'));
        $ragContext = "MEMORIA RAG (Lezioni Apprese):\n";
        $hasExperiences = false;

        foreach ($sports as $sport) {
            $stmt = $this->db->prepare("SELECT outcome, lesson FROM experiences WHERE sport = ? ORDER BY created_at DESC LIMIT 2");
            $stmt->execute([$sport]);
            $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($experiences)) {
                $hasExperiences = true;
                $ragContext .= "Sport: $sport\n";
                foreach ($experiences as $exp) {
                    $status = ($exp['outcome'] === 'won') ? "✅ SUCCESSO" : "❌ FALLIMENTO";
                    $ragContext .= "- $status: {$exp['lesson']}\n";
                }
            }
        }
        if (!$hasExperiences) $ragContext = "";

        $prompt = ($customPrompt ?: "Sei un QUANT TRADER denominato 'Dio'. Non sei uno scommettitore, sei un analista di Price Action (Tape Reading).") . "\n\n" .
            ($ragContext ? $ragContext . "\n" : "") .
            "SITUAZIONE PORTAFOGLIO: Saldo Totale " . number_format($totalBalance, 2) . "€, Saldo Disponibile " . number_format($availableBalance, 2) . "€\n\n" .
            "DATI ASSET (BATCH DI TICKERS):\n" . json_encode($tickers, JSON_PRETTY_PRINT) . "\n\n" .
            "IL TUO VANTAGGIO (Price Action Rules):\n" .
            "1. Analizza 'lastPriceTraded' vs Quota Attuale: Se divergono, identifica il momentum.\n" .
            "2. Analizza lo SCORE vs QUOTE: Se le quote non riflettono correttamente l'andamento del match, identifica il valore.\n" .
            "3. VOLUMI: Un volume alto indica precisione. Se il volume è basso, sii estremamente prudente.\n" .
            "4. IGNORA i nomi delle squadre/atleti. Guarda solo l'efficienza del mercato.\n\n" .
            "STRATEGIA OPERATIVA:\n" .
            "- Quota minima: 1.10.\n" .
            "- Decisione: BACK, LAY o PASS.\n" .
            "- CONFIDENCE: La tua 'confidence' (0-100) deve rispecchiare la PROBABILITÀ REALE stimata. Sii onesto: se la quota è 1.50 (66% imp) e tu stimi il 60%, scrivi confidence 60.\n" .
            "- Fornisci SEMPRE un valore numerico per la 'confidence' (0-100), anche se decidi di passare.\n" .
            "- Confidence >= " . $minConfidence . " per operare.\n\n" .
            "RISPONDI ESCLUSIVAMENTE IN FORMATO JSON (ARRAY DI OGGETTI, uno per ogni ticker in ordine):\n" .
            "[\n" .
            "  {\n" .
            "    \"advice\": \"Runner Name\",\n" .
            "    \"selectionId\": \"ID\",\n" .
            "    \"odds\": 1.80,\n" .
            "    \"confidence\": 90,\n" .
            "    \"motivation\": \"Sintesi tecnica qui.\"\n" .
            "  }\n" .
            "]";

        $response = $this->gemini->analyzeCustom($prompt);

        $json = $response;
        if (preg_match('/```json\s*([\s\S]*?)(?:```|$)/', $response, $matches)) {
            $json = trim($matches[1]);
        }

        $results = json_decode($json, true);
        return is_array($results) ? $results : [];
    }

    private function placeRealBet($ticker, $analysis)
    {
        $marketId = $ticker['marketId'];
        $selectionId = $analysis['selectionId'];
        $odds = $analysis['odds'];
        $stake = $analysis['stake'];

        $res = $this->bf->placeBet($marketId, $selectionId, $odds, $stake);

        if (($res['status'] ?? '') === 'SUCCESS') {
            $betfairId = $res['instructionReports'][0]['betId'] ?? null;

            $stmt = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, motivation, score, type, betfair_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?)");
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
                $ticker['score'] ?? null,
                $betfairId
            ]);

            // Invalidate cache
            $this->cachedPortfolio = null;
            return true;
        }

        return false;
    }

    private function placeVirtualBet($ticker, $analysis)
    {
        // Double check balance before placing
        $available = $this->recalculatePortfolio()['available_balance'];
        $stake = (float)($analysis['stake'] ?? 0);

        if ($available < $stake || $stake < 2.0) {
            return; // Safety guard
        }

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

        // Invalidate cache to ensure subsequent checks in the same loop are accurate
        $this->cachedPortfolio = null;
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
        return $this->recalculatePortfolio();
    }

    private function getRecentBets($validIds = null)
    {
        if ($validIds !== null && empty($validIds)) return [];

        $sql = "SELECT * FROM bets WHERE runner_name NOT LIKE '%PASS%'";
        if ($validIds !== null) {
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $sql .= " AND id IN ($placeholders)";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($validIds ?: []);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logActivity($ticker, $analysis, $action)
    {
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
        $stmt = $this->db->prepare("SELECT * FROM logs ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentExperiences()
    {
        $stmt = $this->db->prepare("SELECT * FROM experiences ORDER BY created_at DESC LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isMatchActiveInDio($eventName)
    {
        if (empty($eventName)) return false;
        $stmt = $this->db->prepare("SELECT event_name FROM bets WHERE status = 'pending'");
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $normCurrent = $this->normalizeEventName($eventName);
        foreach ($pending as $pName) {
            if ($this->normalizeEventName($pName) === $normCurrent) return true;
        }
        return false;
    }

    private function normalizeEventName($name)
    {
        if (!$name) return '';
        $name = strtolower($name);
        $name = preg_replace('/\d+\s*[-:]\s*\d+/', ' ', $name);
        $name = preg_replace('/\b(v|vs|@|-|\/)\b/', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    private function getPerformanceHistory()
    {
        $portfolio = $this->recalculatePortfolio();
        return $portfolio['history'];
    }

    public function syncWithBetfair()
    {
        try {
            if (!$this->bf->isConfigured()) return;

            // Deep Sync se richiesto (es. ricarica manuale dello storico)
            $syncHistory = isset($_GET['sync_history']) && $_GET['sync_history'] === '1';
            $fromDate = $syncHistory ? gmdate('Y-m-d\TH:i:s\Z', time() - (30 * 86400)) : null;

            // Target Sports for Dio
            $targetSports = ['Soccer', 'Tennis', 'Rugby Union', 'Basketball'];
            $allBfOrders = [];

            // 1. Recupera ordini aperti
            $currentRes = $this->bf->getCurrentOrders();
            $currentOrders = $currentRes['currentOrders'] ?? [];
            foreach ($currentOrders as $o) {
                $allBfOrders[$o['betId']] = [
                    'betId' => $o['betId'],
                    'marketId' => $o['marketId'],
                    'selectionId' => $o['selectionId'],
                    'odds' => $o['priceSize']['price'] ?? 0,
                    'stake' => $o['priceSize']['size'] ?? 0,
                    'status' => 'pending',
                    'placedDate' => $o['placedDate'] ?? null,
                ];
            }

            // 2. Recupera ordini definiti (con paginazione se necessario)
            $fromRecord = 0;
            $maxRecords = $syncHistory ? 5000 : 1000;

            do {
                $clearedRes = $this->bf->getClearedOrders(false, $fromDate, $fromRecord);
                $clearedOrders = $clearedRes['clearedOrders'] ?? [];
                $moreAvailable = $clearedRes['moreAvailable'] ?? false;

                foreach ($clearedOrders as $o) {
                    $outcome = strtoupper($o['betOutcome'] ?? '');
                    $status = 'lost';
                    if ($outcome === 'WIN' || $outcome === 'WON') $status = 'won';
                    elseif (in_array($outcome, ['VOIDED', 'LAPSED', 'REMOVED'])) $status = 'void';

                    $allBfOrders[$o['betId']] = [
                        'betId' => $o['betId'],
                        'marketId' => $o['marketId'],
                        'selectionId' => $o['selectionId'],
                        'odds' => $o['priceRequested'] ?? 0,
                        'stake' => $o['sizeSettled'] ?? 0,
                        'status' => $status,
                        'profit' => (float)($o['profit'] ?? 0),
                        'placedDate' => $o['placedDate'] ?? null,
                        'settledDate' => $o['settledDate'] ?? null,
                        'marketName' => $o['itemDescription']['marketDesc'] ?? null,
                        'eventName' => $o['itemDescription']['eventDesc'] ?? null,
                        'sport' => $o['itemDescription']['eventTypeDesc'] ?? null,
                        'runnerName' => $o['itemDescription']['runnerDesc'] ?? null
                    ];
                }

                $fromRecord += count($clearedOrders);
            } while ($moreAvailable && $fromRecord < $maxRecords);

            foreach ($allBfOrders as $betId => $o) {
                // Filter by Dio target sports if possible
                if (isset($o['sport']) && !in_array($o['sport'], $targetSports)) continue;

                $altBetId = (strpos($betId, '1:') === 0) ? substr($betId, 2) : '1:' . $betId;
                $stmt = $this->db->prepare("SELECT id FROM bets WHERE betfair_id = ? OR betfair_id = ?");
                $stmt->execute([$betId, $altBetId]);
                $dbId = $stmt->fetchColumn();

                $placedDate = isset($o['placedDate']) ? gmdate('Y-m-d H:i:s', strtotime($o['placedDate'])) : gmdate('Y-m-d H:i:s');
                $settledDate = isset($o['settledDate']) ? gmdate('Y-m-d H:i:s', strtotime($o['settledDate'])) : null;

                if ($dbId) {
                    $stmtUpdate = $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = ?, betfair_id = ? WHERE id = ?");
                    $stmtUpdate->execute([$o['status'], $o['profit'] ?? 0, $settledDate, $betId, $dbId]);
                } else {
                    // Dio import only if it looks like a Quantum trade or we are importing history
                    // For now, let's import ALL trades from target sports to populate history correctly
                    $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, status, type, betfair_id, profit, settled_at, created_at, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'real', ?, ?, ?, ?, 'Importato da Betfair')");
                    $stmtInsert->execute([
                        $o['marketId'],
                        $o['marketName'] ?? 'Unknown',
                        $o['eventName'] ?? 'Unknown',
                        $o['sport'] ?? 'Unknown',
                        $o['selectionId'],
                        $o['runnerName'] ?? 'Unknown',
                        $o['odds'],
                        $o['stake'],
                        $o['status'],
                        $betId,
                        $o['profit'] ?? 0,
                        $settledDate,
                        $placedDate
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log("Dio Sync Error: " . $e->getMessage());
        }
    }

    private function recalculatePortfolio($actualTotal = null)
    {
        if ($this->cachedPortfolio !== null && $actualTotal === null) {
            return $this->cachedPortfolio;
        }

        $operationalMode = $this->db->query("SELECT value FROM system_state WHERE key = 'operational_mode'")->fetchColumn() ?: 'virtual';

        $stmt = $this->db->prepare("SELECT id, stake, odds, profit, status, created_at, settled_at, type FROM bets WHERE type = ? ORDER BY created_at ASC");
        $stmt->execute([$operationalMode]);
        $allBets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invested = 100.0;
        $currentBalance = 100.0; // Current Available Balance
        $exposure = 0;

        $events = [];
        foreach ($allBets as $bet) {
            $events[] = [
                'time' => $bet['created_at'],
                'type' => 'place',
                'amount' => (float) $bet['stake'],
                'id' => $bet['id']
            ];
            if ($bet['status'] !== 'pending' && $bet['settled_at']) {
                // Per le scommesse reali, usiamo stake + profit (preciso al centesimo su Betfair)
                // Per le virtuali, profit è già calcolato come (stake*odds)-stake o -stake
                $events[] = [
                    'time' => $bet['settled_at'],
                    'type' => 'settle',
                    'status' => $bet['status'],
                    'amount' => (float)$bet['stake'] + (float)$bet['profit'],
                    'id' => $bet['id']
                ];
            }
        }

        // Sort events by time
        usort($events, function ($a, $b) {
            $ta = strtotime($a['time']);
            $tb = strtotime($b['time']);
            if ($ta === $tb) {
                return ($a['type'] === 'place') ? -1 : 1;
            }
            return $ta <=> $tb;
        });

        $wins = 0;
        $settledCount = 0;
        $totalProfit = 0;
        $placedBets = []; // Track which bets were actually placed
        $history = [['t' => 'Start', 'v' => 100.0]];

        $betById = [];
        foreach ($allBets as $bet) {
            $betById[$bet['id']] = $bet;
        }

        foreach ($events as $event) {
            if ($event['type'] === 'place') {
                $stake = $event['amount'];

                // If account is "broke" (Total Balance < 2), simulate a 100€ recharge
                if ($currentBalance + $exposure < 2.0) {
                    $currentBalance += 100.0;
                    $invested += 100.0;
                }

                // If still not enough available funds, SKIP this bet
                if ($currentBalance < $stake) {
                    $placedBets[$event['id']] = false;
                    continue;
                }

                // Place the bet
                $currentBalance -= $stake;
                $exposure += $stake;
                $placedBets[$event['id']] = true;
            } else {
                // Settle
                if (!($placedBets[$event['id']] ?? false)) {
                    continue; // Skip settlement if bet was never placed
                }

                $currentBalance += $event['amount'];
                $bet = $betById[$event['id']];
                $exposure -= (float) $bet['stake'];

                $totalProfit += (float) $bet['profit'];
                if ($event['status'] === 'won') {
                    $wins++;
                }
                $settledCount++;
            }

            $history[] = [
                't' => date('d/m H:i', strtotime($event['time'])),
                'v' => round($currentBalance + $exposure, 2)
            ];
        }

        $placedIds = array_keys(array_filter($placedBets));

        // Adjust for REAL mode if actual balance is provided
        if ($operationalMode === 'real' && $actualTotal !== null) {
            $realTotal = (float)$actualTotal;
            $trackedTotal = $currentBalance + $exposure;
            $offset = $realTotal - $trackedTotal;

            $newHistory = [['t' => 'Start', 'v' => 100.0]];
            if (abs($offset) > 0.01) {
                $newHistory[] = ['t' => 'ADJ', 'v' => round(100.0 + $offset, 2)];
            }

            // Re-trace history with the offset
            $runningTotal = 100.0 + $offset;
            foreach ($events as $event) {
                if ($event['type'] === 'place') {
                    if (!($placedBets[$event['id']] ?? false)) continue;
                    // No change to running total on place (exposure swap)
                } else {
                    if (!($placedBets[$event['id']] ?? false)) continue;
                    $bet = $betById[$event['id']];
                    $runningTotal += (float)$bet['profit'];
                }
                $newHistory[] = [
                    't' => date('d/m H:i', strtotime($event['time'])),
                    'v' => round($runningTotal, 2)
                ];
            }
            $history = $newHistory;
            $currentBalance += $offset; // Adjust current available balance to match real world
        }

        $this->cachedPortfolio = [
            'total_balance' => $currentBalance + $exposure,
            'available_balance' => $currentBalance,
            'exposure_balance' => $exposure,
            'total_profit' => $totalProfit,
            'total_invested' => $invested,
            'roi' => ($invested > 0) ? ($totalProfit / $invested) * 100 : 0,
            'win_rate' => ($settledCount > 0) ? ($wins / $settledCount) * 100 : 0,
            'total_bets' => count($placedIds), // Only count successfully placed bets
            'placed_ids' => $placedIds,
            'history' => $history
        ];

        return $this->cachedPortfolio;
    }
}
