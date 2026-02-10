<?php
// app/GiaNik/Controllers/GiaNikController.php

namespace App\GiaNik\Controllers;

use App\Config\Config;
use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Services\FootballApiService;
use App\Services\BasketballApiService;
use App\Services\NbaApiService;
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

            // --- Pre-fetch Enrichment Data ---
            // API-Football Live Enrichment
            $api = new FootballApiService();
            $apiLiveRes = $api->fetchLiveMatches();
            $apiLiveMatches = $apiLiveRes['response'] ?? [];

            // API-Basketball Live Enrichment
            $apiBasket = new BasketballApiService();
            $apiBasketLiveRes = $apiBasket->fetchLiveGames();
            $apiBasketLiveMatches = $apiBasketLiveRes['response'] ?? [];

            // API-NBA Live Enrichment
            $apiNba = new NbaApiService();
            $apiNbaLiveRes = $apiNba->fetchLiveGames();
            $apiNbaLiveMatches = $apiNbaLiveRes['response'] ?? [];

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
                    'runners' => [],
                    'status_label' => 'LIVE',
                    'score' => null,
                    'has_api_data' => false
                ];

                // --- Enrichment for scores/status ---
                if ($sport === 'Soccer' || $sport === 'Football') {
                    $match = $this->searchInFixtureList($m['event'], $apiLiveMatches);
                    if ($match) {
                        $m['score'] = ($match['goals']['home'] ?? 0) . '-' . ($match['goals']['away'] ?? 0);
                        $m['status_label'] = ($match['fixture']['status']['short'] ?? 'LIVE') . ' ' . ($match['fixture']['status']['elapsed'] ?? 0) . "'";
                        $m['has_api_data'] = true;
                    }
                } elseif ($sport === 'Basketball') {
                    $isNba = stripos($m['competition'], 'NBA') !== false || stripos($m['event'], 'NBA') !== false;

                    if ($isNba) {
                        $match = $this->searchInNbaGameList($m['event'], $apiNbaLiveMatches);
                        if ($match) {
                            $m['score'] = ($match['scores']['home']['points'] ?? 0) . '-' . ($match['scores']['away']['points'] ?? 0);
                            $m['status_label'] = ($match['status']['short'] ?? 'LIVE') . ' ' . ($match['status']['clock'] ?? '');
                            $m['has_api_data'] = true;
                        }
                    } else {
                        $match = $this->searchInBasketballGameList($m['event'], $apiBasketLiveMatches);
                        if ($match) {
                            $m['score'] = ($match['scores']['home']['total'] ?? 0) . '-' . ($match['scores']['away']['total'] ?? 0);
                            $m['status_label'] = ($match['status']['short'] ?? 'LIVE') . ' ' . ($match['status']['timer'] ?? '');
                            $m['has_api_data'] = true;
                        }
                    }
                } else {
                    // Try to get from Betfair Market Definition or Event Name
                    if (isset($mb['marketDefinition'])) {
                        $def = $mb['marketDefinition'];
                        // Extract scores from event name if present (e.g. "Team A 1-0 Team B")
                        if (preg_match('/(\d+)\s*-\s*(\d+)/', $m['event'], $scoreMatches)) {
                            $m['score'] = $scoreMatches[1] . '-' . $scoreMatches[2];
                        }

                        // Extract Quarter/Set info from event name (e.g. "Team A v Team B (Q3)")
                        if (preg_match('/\((Q[1-4]|Set\s*[1-5]|HT)\)/i', $m['event'], $periodMatches)) {
                            $m['status_label'] = strtoupper($periodMatches[1]);
                        } elseif (isset($def['score'])) {
                            // Try to construct set/score label from Betfair structure
                            $s = $def['score'];
                            if (isset($s['homeSets'], $s['awaySets'])) {
                                $m['status_label'] = $s['homeSets'] . '-' . $s['awaySets'] . ' SET';
                            }
                        } elseif (isset($def['inPlay']) && $def['inPlay']) {
                            $m['status_label'] = 'LIVE';
                        }
                    }
                }

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

            // Account Funds (Real)
            $account = ['available' => 0, 'exposure' => 0];
            $funds = $this->bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];
            $account['available'] = $funds['availableToBetBalance'] ?? 0;
            $account['exposure'] = abs($funds['exposure'] ?? 0);

            // Virtual Balance Calculation
            $initialBalance = 100.00;
            $stmtProfit = $this->db->query("SELECT SUM(profit) FROM bets WHERE type = 'virtual' AND status IN ('won', 'lost')");
            $totalProfit = (float)$stmtProfit->fetchColumn();

            $stmtExposure = $this->db->query("SELECT SUM(stake) FROM bets WHERE type = 'virtual' AND status = 'pending'");
            $virtualExposure = (float)$stmtExposure->fetchColumn();

            $virtualAccount = [
                'total' => $initialBalance + $totalProfit,
                'exposure' => $virtualExposure,
                'available' => ($initialBalance + $totalProfit) - $virtualExposure
            ];

            // Trigger settlement for any newly closed markets
            $this->settleBets();

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

            $eventName = $mc['event']['name'] ?? 'Unknown';
            $competitionName = $mc['competition']['name'] ?? '';
            $sportName = $mc['eventType']['name'] ?? '';
            $eventId = $mc['event']['id'] ?? null;

            // Fallback: if names are missing, try listEvents if we have an event ID
            if (($eventName === 'Unknown' || empty($sportName)) && $eventId) {
                $resEv = $this->bf->request('listEvents', ['filter' => ['eventIds' => [$eventId]]]);
                if (!empty($resEv['result'][0]['event'])) {
                    $eventName = $resEv['result'][0]['event']['name'];
                }

                // Also try to get event type if missing
                if (empty($sportName)) {
                    $resEt = $this->bf->request('listEventTypes', ['filter' => ['eventIds' => [$eventId]]]);
                    $sportName = $resEt['result'][0]['eventType']['name'] ?? '';
                }
            }

            $event = [
                'marketId' => $marketId,
                'event' => $eventName,
                'competition' => $competitionName,
                'sport' => $sportName,
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

            // Use Virtual Budget for GiaNik Analysis
            $initialBalance = 100.00;
            $stmtProfit = $this->db->query("SELECT SUM(profit) FROM bets WHERE type = 'virtual' AND status IN ('won', 'lost')");
            $totalProfit = (float)$stmtProfit->fetchColumn();

            $stmtExposure = $this->db->query("SELECT SUM(stake) FROM bets WHERE type = 'virtual' AND status = 'pending'");
            $virtualExposure = (float)$stmtExposure->fetchColumn();

            $available = ($initialBalance + $totalProfit) - $virtualExposure;
            $total = $initialBalance + $totalProfit;

            $balance = [
                'available_balance' => $available,
                'current_portfolio' => $total
            ];

            // --- ENRICHMENT WITH API-FOOTBALL / API-BASKETBALL ---
            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
            } elseif ($event['sport'] === 'Basketball') {
                $event['api_basketball'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
            }

            $gemini = new GeminiService();
            // GiaNik option uses candidates[0] which now includes API-Football data if found
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

    private function findMatchingFixture($bfEventName, $sport, $preFetchedLive = null)
    {
        $api = new FootballApiService();

        // 1. Try Live matches first
        $liveFixtures = $preFetchedLive;
        if ($liveFixtures === null) {
            $live = $api->fetchLiveMatches();
            $liveFixtures = $live['response'] ?? [];
        }

        if (!empty($liveFixtures)) {
            $match = $this->searchInFixtureList($bfEventName, $liveFixtures);
            if ($match) return $match;
        }

        // 2. If not in live all, try searching by team if we can extract names
        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $bfEventName);
        if (count($bfTeams) >= 2) {
            $bfHome = $this->normalizeTeamName($bfTeams[0]);

            // Search for home team to get its fixtures for today
            $teamSearch = $api->fetchTeams(['name' => $bfHome]);
            if (!empty($teamSearch['response'])) {
                foreach (array_slice($teamSearch['response'], 0, 3) as $teamItem) {
                    $teamId = $teamItem['team']['id'];
                    $fixtures = $api->request("/fixtures?team=$teamId&date=" . date('Y-m-d'));

                    if (!empty($fixtures['response'])) {
                        $match = $this->searchInFixtureList($bfEventName, $fixtures['response']);
                        if ($match) return $match;
                    }
                }
            }
        }

        return null;
    }

    private function searchInFixtureList($bfEventName, $fixtures)
    {
        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $bfEventName);
        if (count($bfTeams) < 2) return null;

        $bfHome = $this->normalizeTeamName($bfTeams[0]);
        $bfAway = $this->normalizeTeamName($bfTeams[1]);

        foreach ($fixtures as $item) {
            $apiHome = $this->normalizeTeamName($item['teams']['home']['name']);
            $apiAway = $this->normalizeTeamName($item['teams']['away']['name']);

            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) ||
                ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) {
                return $item;
            }
        }
        return null;
    }

    private function normalizeTeamName($name)
    {
        $name = strtolower($name);
        // Common abbreviations
        $replacements = [
            'man ' => 'manchester ',
            'man utd' => 'manchester united',
            'man city' => 'manchester city',
            'st ' => 'saint ',
            'int ' => 'inter ',
            'ath ' => 'athletic ',
            'atl ' => 'atletico '
        ];
        foreach ($replacements as $search => $replace) {
            $name = str_replace($search, $replace, $name);
        }

        $remove = ['fc', 'united', 'city', 'town', 'real', 'atlético', 'atletico', 'inter', 'u23', 'u21', 'u19', 'women', 'donne', 'femminile', 'sports', 'sc'];
        foreach ($remove as $r) {
            $name = preg_replace('/\b' . preg_quote($r, '/') . '\b/i', '', $name);
        }
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function isMatch($n1, $n2)
    {
        if (empty($n1) || empty($n2)) return false;
        if (strpos($n1, $n2) !== false || strpos($n2, $n1) !== false) return true;
        if (levenshtein($n1, $n2) < 3) return true;
        return false;
    }

    public function placeBet()
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $marketId = $input['marketId'] ?? null;
            $selectionId = $input['selectionId'] ?? null;
            $odds = $input['odds'] ?? null;
            $stake = (float)($input['stake'] ?? 2.0);
            if ($stake < 2.0) $stake = 2.0;
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

    public function autoProcess()
    {
        header('Content-Type: application/json');
        $results = ['scanned' => 0, 'new_bets' => 0, 'errors' => []];
        try {
            // 1. Get Live Events
            $eventTypesRes = $this->bf->getEventTypes();
            $eventTypeIds = array_map(fn($et) => $et['eventType']['id'], $eventTypesRes['result'] ?? []);
            $liveEventsRes = $this->bf->getLiveEvents($eventTypeIds);
            $events = $liveEventsRes['result'] ?? [];

            // Pre-fetch live fixtures from API-Football once to use for matching
            $api = new FootballApiService();
            $apiLiveRes = $api->fetchLiveMatches();
            $apiLiveFixtures = $apiLiveRes['response'] ?? [];

            if (empty($events)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessun evento live']);
                return;
            }

            // 2. Fetch multiple markets per event for deep technical analysis
            $eventIds = array_map(fn($e) => $e['event']['id'], $events);
            $marketTypes = ['MATCH_ODDS', 'WINNER', 'MONEYLINE', 'OVER_UNDER_25', 'BOTH_TEAMS_TO_SCORE', 'DOUBLE_CHANCE'];
            $marketCatalogues = [];
            $chunks = array_chunk($eventIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketCatalogues($chunk, 200, $marketTypes);
                if (isset($res['result'])) $marketCatalogues = array_merge($marketCatalogues, $res['result']);
            }

            // Group market catalogues by event ID
            $eventMarketsMap = [];
            foreach ($marketCatalogues as $mc) {
                $eid = $mc['event']['id'];
                $eventMarketsMap[$eid][] = $mc;
            }

            // Filter out events where we already have a pending bet
            $stmtPending = $this->db->prepare("SELECT DISTINCT event_name FROM bets WHERE status = 'pending'");
            $stmtPending->execute();
            $pendingEventNames = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

            $results['scanned'] = 0;
            // Iterate events instead of markets
            $eventCounter = 0;
            foreach ($eventMarketsMap as $eid => $catalogues) {
                if ($eventCounter >= 3) break; // Limit concurrent events

                $mainEvent = $catalogues[0];
                if (in_array($mainEvent['event']['name'], $pendingEventNames)) continue;

                try {
                    $results['scanned']++;
                    $eventCounter++;

                    // Prepare comprehensive event data for Gemini
                    $marketIds = array_map(fn($mc) => $mc['marketId'], $catalogues);
                    $booksRes = $this->bf->getMarketBooks($marketIds);
                    $books = $booksRes['result'] ?? [];

                    $booksMap = [];
                    foreach ($books as $b) $booksMap[$b['marketId']] = $b;

                    $event = [
                        'event' => $mainEvent['event']['name'],
                        'competition' => $mainEvent['competition']['name'] ?? '',
                        'sport' => $mainEvent['eventType']['name'] ?? '',
                        'markets' => []
                    ];

                    foreach ($catalogues as $mc) {
                        $mId = $mc['marketId'];
                        if (!isset($booksMap[$mId])) continue;
                        $book = $booksMap[$mId];

                        $m = [
                            'marketId' => $mId,
                            'marketName' => $mc['marketName'],
                            'totalMatched' => $book['totalMatched'],
                            'runners' => []
                        ];

                        foreach ($book['runners'] as $r) {
                            $mR = array_filter($mc['runners'], fn($rm) => $rm['selectionId'] === $r['selectionId']);
                            $name = reset($mR)['runnerName'] ?? 'Unknown';
                            $m['runners'][] = [
                                'selectionId' => $r['selectionId'],
                                'name' => $name,
                                'back' => $r['ex']['availableToBack'][0]['price'] ?? 0
                            ];
                        }
                        $event['markets'][] = $m;
                    }

                    // Enriched with API-Football/NBA if available
                    if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                        $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], $apiLiveFixtures, $event['competition']);
                    } elseif ($event['sport'] === 'Basketball') {
                        $event['api_basketball'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
                    }

                    // Calculate current virtual balance for Gemini context
                    $vInit = 100.0;
                    $vProf = (float)$this->db->query("SELECT SUM(profit) FROM bets WHERE type = 'virtual' AND status IN ('won', 'lost')")->fetchColumn();
                    $vExp = (float)$this->db->query("SELECT SUM(stake) FROM bets WHERE type = 'virtual' AND status = 'pending'")->fetchColumn();

                    $gemini = new GeminiService();
                    $predictionRaw = $gemini->analyze([$event], [
                        'is_gianik' => true,
                        'available_balance' => ($vInit + $vProf) - $vExp,
                        'current_portfolio' => $vInit + $vProf
                    ]);

                    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                        $analysis = json_decode($matches[1], true);
                        if ($analysis && !empty($analysis['marketId']) && !empty($analysis['advice']) && ($analysis['confidence'] ?? 0) >= 70) {

                            // Find the selected market and its runners
                            $selectedMarket = null;
                            foreach ($event['markets'] as $m) {
                                if ($m['marketId'] === $analysis['marketId']) {
                                    $selectedMarket = $m;
                                    break;
                                }
                            }

                            if (!$selectedMarket) continue;

                            // Check if we have enough virtual budget
                            $stake = (float)($analysis['stake'] ?? 2.0);
                            if ($stake < 2.0) $stake = 2.0;
                            if (($vInit + $vProf - $vExp) < $stake) continue;

                            $runnerName = $analysis['advice'];
                            $runners = array_map(fn($r) => ['runnerName' => $r['name'], 'selectionId' => $r['selectionId']], $selectedMarket['runners']);
                            $selectionId = $this->bf->mapAdviceToSelection($runnerName, $runners);

                            if ($selectionId) {
                                $reasoning = trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));
                                $motivation = $analysis['motivation'] ?? $reasoning;

                                $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, event_name, sport, selection_id, runner_name, odds, stake, type, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmtInsert->execute([
                                    $analysis['marketId'],
                                    $event['event'],
                                    $event['sport'],
                                    $selectionId,
                                    $runnerName,
                                    $analysis['odds'],
                                    $stake,
                                    'virtual',
                                    $motivation
                                ]);
                                $results['new_bets']++;
                            }
                        }
                    }
                } catch (\Throwable $ex) {
                    $results['errors'][] = $ex->getMessage();
                }
            }

            // Also trigger settlement
            $this->settleBets();

            echo json_encode(['status' => 'success', 'results' => $results]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function settleBets()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND created_at < datetime('now', '-5 minutes')");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pending)) return;

            foreach ($pending as $bet) {
                $marketId = $bet['market_id'];
                $res = $this->bf->getMarketBooks([$marketId]);
                $mb = $res['result'][0] ?? null;

                if ($mb && $mb['status'] === 'CLOSED') {
                    // Find winner
                    $winner = null;
                    foreach ($mb['runners'] as $r) {
                        if (($r['status'] ?? '') === 'WINNER') {
                            $winner = $r['selectionId'];
                            break;
                        }
                    }

                    if ($winner) {
                        $isWin = ($winner == $bet['selection_id']);
                        $status = $isWin ? 'won' : 'lost';
                        $profit = $isWin ? ($bet['stake'] * ($bet['odds'] - 1)) : -$bet['stake'];

                        $update = $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $update->execute([$status, $profit, $bet['id']]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("GiaNik Settlement Error: " . $e->getMessage());
        }
    }

    private function enrichWithApiData($bfEventName, $sport, $preFetchedLive = null, $competition = '')
    {
        if ($sport === 'Soccer' || $sport === 'Football') {
            $apiMatch = $this->findMatchingFixture($bfEventName, $sport, $preFetchedLive);
            if (!$apiMatch) return null;

            $api = new FootballApiService();
            $fixtureId = $apiMatch['fixture']['id'];

            // Comprehensive fixture details
            $details = $api->fetchFixtureDetails($fixtureId);
            $fullFixture = $details['response'][0] ?? $apiMatch;

            $h2h = $api->fetchH2H($apiMatch['teams']['home']['id'] . '-' . $apiMatch['teams']['away']['id']);

            $standings = null;
            if (isset($apiMatch['league']['id'], $apiMatch['league']['season'])) {
                $stRes = $api->fetchStandings($apiMatch['league']['id'], $apiMatch['league']['season']);
                $standings = $stRes['response'][0]['league']['standings'] ?? null;
            }

            $predictions = $api->fetchPredictions($fixtureId);

            return [
                'fixture' => $fullFixture,
                'h2h' => $h2h['response'] ?? [],
                'standings' => $standings,
                'predictions' => $predictions['response'][0] ?? null
            ];
        } elseif ($sport === 'Basketball') {
            $isNba = stripos($competition, 'NBA') !== false || stripos($bfEventName, 'NBA') !== false;

            if ($isNba) {
                $apiMatch = $this->findMatchingNbaFixture($bfEventName, $preFetchedLive);
                if (!$apiMatch) return null;

                $api = new NbaApiService();
                $gameId = $apiMatch['id'];
                $stats = $api->fetchStatistics($gameId);

                return [
                    'game' => $apiMatch,
                    'statistics' => $stats['response'] ?? []
                ];
            } else {
                $apiMatch = $this->findMatchingBasketballFixture($bfEventName, $preFetchedLive);
                if (!$apiMatch) return null;

                $api = new BasketballApiService();

                // For basketball, we keep it light due to request limits
                $h2h = $api->fetchH2H($apiMatch['teams']['home']['id'] . '-' . $apiMatch['teams']['away']['id']);

                $standings = null;
                if (isset($apiMatch['league']['id'], $apiMatch['league']['season'])) {
                    $stRes = $api->fetchStandings($apiMatch['league']['id'], $apiMatch['league']['season']);
                    $standings = $stRes['response'] ?? null;
                }

                return [
                    'game' => $apiMatch,
                    'h2h' => $h2h['response'] ?? [],
                    'standings' => $standings
                ];
            }
        }
        return null;
    }

    private function findMatchingNbaFixture($bfEventName, $preFetchedLive = null)
    {
        $api = new NbaApiService();
        $liveGames = $preFetchedLive;
        if ($liveGames === null) {
            $res = $api->fetchLiveGames();
            $liveGames = $res['response'] ?? [];
        }

        if (!empty($liveGames)) {
            $match = $this->searchInNbaGameList($bfEventName, $liveGames);
            if ($match) return $match;
        }
        return null;
    }

    private function searchInNbaGameList($bfEventName, $games)
    {
        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $bfEventName);
        if (count($bfTeams) < 2) return null;

        $bfHome = $this->normalizeTeamName($bfTeams[0]);
        $bfAway = $this->normalizeTeamName($bfTeams[1]);

        foreach ($games as $item) {
            $apiHome = $this->normalizeTeamName($item['teams']['home']['name']);
            $apiAway = $this->normalizeTeamName($item['teams']['away']['name']);

            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) ||
                ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) {
                return $item;
            }
        }
        return null;
    }

    private function findMatchingBasketballFixture($bfEventName, $preFetchedLive = null)
    {
        $api = new BasketballApiService();
        $liveGames = $preFetchedLive;
        if ($liveGames === null) {
            $res = $api->fetchLiveGames();
            $liveGames = $res['response'] ?? [];
        }

        if (!empty($liveGames)) {
            $match = $this->searchInBasketballGameList($bfEventName, $liveGames);
            if ($match) return $match;
        }
        return null;
    }

    private function searchInBasketballGameList($bfEventName, $games)
    {
        $bfTeams = preg_split('/\s+(v|vs|@)\s+/i', $bfEventName);
        if (count($bfTeams) < 2) return null;

        $bfHome = $this->normalizeTeamName($bfTeams[0]);
        $bfAway = $this->normalizeTeamName($bfTeams[1]);

        foreach ($games as $item) {
            $apiHome = $this->normalizeTeamName($item['teams']['home']['name']);
            $apiAway = $this->normalizeTeamName($item['teams']['away']['name']);

            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) ||
                ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) {
                return $item;
            }
        }
        return null;
    }

    public function recentBets()
    {
        try {
            $statusFilter = $_GET['status'] ?? 'all';
            $sql = "SELECT * FROM bets";
            $params = [];

            if ($statusFilter === 'won') {
                $sql .= " WHERE status = 'won'";
            } elseif ($statusFilter === 'lost') {
                $sql .= " WHERE status = 'lost'";
            }

            $sql .= " ORDER BY created_at DESC LIMIT 20";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            require __DIR__ . '/../Views/partials/recent_bets_sidebar.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-2 text-[10px]">' . $e->getMessage() . '</div>';
        }
    }

    public function betDetails($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE id = ?");
            $stmt->execute([$id]);
            $bet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bet) {
                echo '<div class="p-10 text-center text-danger font-black uppercase italic">Scommessa non trovata.</div>';
                return;
            }

            require __DIR__ . '/../Views/partials/modals/bet_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }
}
