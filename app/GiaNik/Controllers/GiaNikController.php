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
            $api = new FootballApiService();
            $apiLiveRes = $api->fetchLiveMatches();
            $apiLiveMatches = $apiLiveRes['response'] ?? [];

            $apiBasket = new BasketballApiService();
            $apiBasketLiveRes = $apiBasket->fetchLiveGames();
            $apiBasketLiveMatches = $apiBasketLiveRes['response'] ?? [];

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

                // --- Enrichment ---
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
                }

                // Merge runners
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

            uksort($groupedMatches, function($a, $b) use ($groupedMatches) {
                return count($groupedMatches[$b]) <=> count($groupedMatches[$a]);
            });

            // Funds
            $account = ['available' => 0, 'exposure' => 0];
            $funds = $this->bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];
            $account['available'] = $funds['availableToBetBalance'] ?? 0;
            $account['exposure'] = abs($funds['exposure'] ?? 0);

            // Virtual
            $initialBalance = 100.00;
            $totalProfit = (float)$this->db->query("SELECT SUM(profit) FROM bets WHERE status IN ('won', 'lost')")->fetchColumn();
            $virtualExposure = (float)$this->db->query("SELECT SUM(stake) FROM bets WHERE status = 'pending'")->fetchColumn();

            $virtualAccount = [
                'total' => $initialBalance + $totalProfit,
                'exposure' => $virtualExposure,
                'available' => ($initialBalance + $totalProfit) - $virtualExposure
            ];

            $this->settleBets();
            require __DIR__ . '/../Views/partials/gianik_live.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore GiaNik Live: ' . $e->getMessage() . '</div>';
        }
    }

    public function analyze($marketId)
    {
        try {
            $resCat = $this->bf->request('listMarketCatalogue', [
                'filter' => ['marketIds' => [$marketId]],
                'marketProjection' => ['EVENT', 'COMPETITION', 'EVENT_TYPE']
            ]);
            $initialMc = $resCat['result'][0] ?? null;

            if (!$initialMc) {
                echo '<div class="glass p-10 rounded-3xl text-center border-danger/20 text-danger uppercase font-black italic">Evento non trovato.</div>';
                return;
            }

            $eventId = $initialMc['event']['id'];
            $eventName = $initialMc['event']['name'];
            $competitionName = $initialMc['competition']['name'] ?? '';
            $sportName = $initialMc['eventType']['name'] ?? '';

            $marketTypes = ['MATCH_ODDS', 'WINNER', 'MONEYLINE', 'OVER_UNDER_05', 'OVER_UNDER_15', 'OVER_UNDER_25', 'OVER_UNDER_35', 'OVER_UNDER_45', 'BOTH_TEAMS_TO_SCORE', 'DOUBLE_CHANCE'];
            $allMcRes = $this->bf->getMarketCatalogues([$eventId], 20, $marketTypes);
            $catalogues = $allMcRes['result'] ?? [$initialMc];

            $marketIds = array_map(fn($mc) => $mc['marketId'], $catalogues);
            $booksRes = $this->bf->getMarketBooks($marketIds);
            $booksMap = [];
            foreach ($booksRes['result'] ?? [] as $b) $booksMap[$b['marketId']] = $b;

            $event = [
                'event' => $eventName,
                'competition' => $competitionName,
                'sport' => $sportName,
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

            $initialBalance = 100.00;
            $totalProfit = (float)$this->db->query("SELECT SUM(profit) FROM bets WHERE status IN ('won', 'lost')")->fetchColumn();
            $virtualExposure = (float)$this->db->query("SELECT SUM(stake) FROM bets WHERE status = 'pending'")->fetchColumn();

            $available = ($initialBalance + $totalProfit) - $virtualExposure;
            $total = $initialBalance + $totalProfit;

            $balance = ['available_balance' => $available, 'current_portfolio' => $total];

            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
            } elseif ($event['sport'] === 'Basketball') {
                $event['api_basketball'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
            }

            $gemini = new GeminiService();
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
            $marketName = $input['marketName'] ?? 'Unknown';
            $selectionId = $input['selectionId'] ?? null;
            $odds = $input['odds'] ?? null;
            $stake = (float)($input['stake'] ?? 2.0);
            if ($stake < 2.0) $stake = 2.0;
            $type = $input['type'] ?? 'virtual';
            $eventName = $input['eventName'] ?? 'Unknown';
            $sport = $input['sport'] ?? 'Unknown';
            $runnerName = $input['runnerName'] ?? 'Unknown';
            $motivation = $input['motivation'] ?? '';

            if (!$marketId || !$selectionId || !$odds) {
                echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
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

            $stmt = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, type, betfair_id, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$marketId, $marketName, $eventName, $sport, $selectionId, $runnerName, $odds, $stake, $type, $betfairId, $motivation]);

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
            $eventTypesRes = $this->bf->getEventTypes();
            $eventTypeIds = array_map(fn($et) => $et['eventType']['id'], $eventTypesRes['result'] ?? []);
            $liveEventsRes = $this->bf->getLiveEvents($eventTypeIds);
            $events = $liveEventsRes['result'] ?? [];

            $api = new FootballApiService();
            $apiLiveRes = $api->fetchLiveMatches();
            $apiLiveFixtures = $apiLiveRes['response'] ?? [];

            if (empty($events)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessun evento live']);
                return;
            }

            $eventIds = array_map(fn($e) => $e['event']['id'], $events);
            $marketTypes = ['MATCH_ODDS', 'WINNER', 'MONEYLINE', 'OVER_UNDER_05', 'OVER_UNDER_15', 'OVER_UNDER_25', 'OVER_UNDER_35', 'OVER_UNDER_45', 'BOTH_TEAMS_TO_SCORE', 'DOUBLE_CHANCE'];
            $marketCatalogues = [];
            $chunks = array_chunk($eventIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketCatalogues($chunk, 200, $marketTypes);
                if (isset($res['result'])) $marketCatalogues = array_merge($marketCatalogues, $res['result']);
            }

            $eventMarketsMap = [];
            foreach ($marketCatalogues as $mc) {
                $eid = $mc['event']['id'];
                $eventMarketsMap[$eid][] = $mc;
            }

            $stmtPending = $this->db->prepare("SELECT DISTINCT event_name FROM bets WHERE status = 'pending'");
            $stmtPending->execute();
            $pendingEventNames = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

            $eventCounter = 0;
            foreach ($eventMarketsMap as $eid => $catalogues) {
                if ($eventCounter >= 3) break;
                $mainEvent = $catalogues[0];
                if (in_array($mainEvent['event']['name'], $pendingEventNames)) continue;

                try {
                    $results['scanned']++;
                    $eventCounter++;

                    $marketIds = array_map(fn($mc) => $mc['marketId'], $catalogues);
                    $booksRes = $this->bf->getMarketBooks($marketIds);
                    $booksMap = [];
                    foreach ($booksRes['result'] ?? [] as $b) $booksMap[$b['marketId']] = $b;

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

                    if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                        $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], $apiLiveFixtures, $event['competition']);
                    } elseif ($event['sport'] === 'Basketball') {
                        $event['api_basketball'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
                    }

                    $vInit = 100.0;
                    $vProf = (float)$this->db->query("SELECT SUM(profit) FROM bets WHERE status IN ('won', 'lost')")->fetchColumn();
                    $vExp = (float)$this->db->query("SELECT SUM(stake) FROM bets WHERE status = 'pending'")->fetchColumn();

                    $gemini = new GeminiService();
                    $predictionRaw = $gemini->analyze([$event], ['is_gianik' => true, 'available_balance' => ($vInit + $vProf) - $vExp, 'current_portfolio' => $vInit + $vProf]);

                    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                        $analysis = json_decode($matches[1], true);
                        if ($analysis && !empty($analysis['marketId']) && !empty($analysis['advice']) && ($analysis['confidence'] ?? 0) >= 70) {
                            $selectedMarket = null;
                            foreach ($event['markets'] as $m) { if ($m['marketId'] === $analysis['marketId']) { $selectedMarket = $m; break; } }
                            if (!$selectedMarket) continue;

                            $stake = (float)($analysis['stake'] ?? 2.0);
                            if ($stake < 2.0) $stake = 2.0;
                            if (($vInit + $vProf - $vExp) < $stake) continue;

                            $runners = array_map(fn($r) => ['runnerName' => $r['name'], 'selectionId' => $r['selectionId']], $selectedMarket['runners']);
                            $selectionId = $this->bf->mapAdviceToSelection($analysis['advice'], $runners);

                            if ($selectionId) {
                                $motivation = $analysis['motivation'] ?? trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));
                                $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, type, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmtInsert->execute([$analysis['marketId'], $selectedMarket['marketName'], $event['event'], $event['sport'], $selectionId, $analysis['advice'], $analysis['odds'], $stake, 'virtual', $motivation]);
                                $results['new_bets']++;
                            }
                        }
                    }
                } catch (\Throwable $ex) { $results['errors'][] = $ex->getMessage(); }
            }
            $this->settleBets();
            echo json_encode(['status' => 'success', 'results' => $results]);
        } catch (\Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    }

    public function settleBets()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND created_at < datetime('now', '-5 minutes')");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pending as $bet) {
                $res = $this->bf->getMarketBooks([$bet['market_id']]);
                $mb = $res['result'][0] ?? null;
                if ($mb && $mb['status'] === 'CLOSED') {
                    $winner = null;
                    foreach ($mb['runners'] as $r) { if (($r['status'] ?? '') === 'WINNER') { $winner = $r['selectionId']; break; } }
                    if ($winner) {
                        $isWin = ($winner == $bet['selection_id']);
                        $status = $isWin ? 'won' : 'lost';
                        $profit = $isWin ? ($bet['stake'] * ($bet['odds'] - 1)) : -$bet['stake'];
                        $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $profit, $bet['id']]);
                    }
                }
            }
        } catch (\Throwable $e) { error_log("GiaNik Settlement Error: " . $e->getMessage()); }
    }

    private function enrichWithApiData($bfEventName, $sport, $preFetchedLive = null, $competition = '')
    {
        if ($sport === 'Soccer' || $sport === 'Football') {
            $apiMatch = $this->findMatchingFixture($bfEventName, $sport, $preFetchedLive);
            if (!$apiMatch) return null;
            $api = new FootballApiService();
            $fullFixture = $api->fetchFixtureDetails($apiMatch['fixture']['id'])['response'][0] ?? $apiMatch;
            $h2h = $api->fetchH2H($apiMatch['teams']['home']['id'] . '-' . $apiMatch['teams']['away']['id']);
            $standings = (isset($apiMatch['league']['id'], $apiMatch['league']['season'])) ? $api->fetchStandings($apiMatch['league']['id'], $apiMatch['league']['season'])['response'][0]['league']['standings'] ?? null : null;
            return ['fixture' => $fullFixture, 'h2h' => $h2h['response'] ?? [], 'standings' => $standings, 'predictions' => $api->fetchPredictions($apiMatch['fixture']['id'])['response'][0] ?? null];
        } elseif ($sport === 'Basketball') {
            $isNba = stripos($competition, 'NBA') !== false || stripos($bfEventName, 'NBA') !== false;
            if ($isNba) {
                $apiMatch = $this->findMatchingNbaFixture($bfEventName, $preFetchedLive);
                if (!$apiMatch) return null;
                $api = new NbaApiService();
                return ['game' => $apiMatch, 'statistics' => $api->fetchStatistics($apiMatch['id'])['response'] ?? []];
            } else {
                $apiMatch = $this->findMatchingBasketballFixture($bfEventName, $preFetchedLive);
                if (!$apiMatch) return null;
                $api = new BasketballApiService();
                $h2h = $api->fetchH2H($apiMatch['teams']['home']['id'] . '-' . $apiMatch['teams']['away']['id']);
                $standings = (isset($apiMatch['league']['id'], $apiMatch['league']['season'])) ? $api->fetchStandings($apiMatch['league']['id'], $apiMatch['league']['season'])['response'] ?? null : null;
                return ['game' => $apiMatch, 'h2h' => $h2h['response'] ?? [], 'standings' => $standings];
            }
        }
        return null;
    }

    private function findMatchingFixture($bfEventName, $sport, $preFetchedLive = null)
    {
        $liveFixtures = $preFetchedLive ?? (new FootballApiService())->fetchLiveMatches()['response'] ?? [];
        return $this->searchInFixtureList($bfEventName, $liveFixtures);
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
            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) || ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) return $item;
        }
        return null;
    }

    private function findMatchingNbaFixture($bfEventName, $preFetchedLive = null)
    {
        $liveGames = $preFetchedLive ?? (new NbaApiService())->fetchLiveGames()['response'] ?? [];
        return $this->searchInNbaGameList($bfEventName, $liveGames);
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
            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) || ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) return $item;
        }
        return null;
    }

    private function findMatchingBasketballFixture($bfEventName, $preFetchedLive = null)
    {
        $liveGames = $preFetchedLive ?? (new BasketballApiService())->fetchLiveGames()['response'] ?? [];
        return $this->searchInBasketballGameList($bfEventName, $liveGames);
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
            if (($this->isMatch($bfHome, $apiHome) && $this->isMatch($bfAway, $apiAway)) || ($this->isMatch($bfHome, $apiAway) && $this->isMatch($bfAway, $apiHome))) return $item;
        }
        return null;
    }

    private function normalizeTeamName($name)
    {
        $name = strtolower($name);
        $replacements = ['man ' => 'manchester ', 'man utd' => 'manchester united', 'man city' => 'manchester city', 'st ' => 'saint ', 'int ' => 'inter ', 'ath ' => 'athletic ', 'atl ' => 'atletico '];
        foreach ($replacements as $search => $replace) $name = str_replace($search, $replace, $name);
        $remove = ['fc', 'united', 'city', 'town', 'real', 'atlético', 'atletico', 'inter', 'u23', 'u21', 'u19', 'women', 'donne', 'femminile', 'sports', 'sc'];
        foreach ($remove as $r) $name = preg_replace('/\b' . preg_quote($r, '/') . '\b/i', '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function isMatch($n1, $n2)
    {
        if (empty($n1) || empty($n2)) return false;
        if (strpos($n1, $n2) !== false || strpos($n2, $n1) !== false) return true;
        if (levenshtein($n1, $n2) < 3) return true;
        return false;
    }

    public function recentBets()
    {
        try {
            $statusFilter = $_GET['status'] ?? 'all';
            $sql = "SELECT * FROM bets";
            if ($statusFilter === 'won') $sql .= " WHERE status = 'won'";
            elseif ($statusFilter === 'lost') $sql .= " WHERE status = 'lost'";
            $sql .= " ORDER BY created_at DESC LIMIT 20";
            $bets = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            require __DIR__ . '/../Views/partials/recent_bets_sidebar.php';
        } catch (\Throwable $e) { echo '<div class="text-danger p-2 text-[10px]">' . $e->getMessage() . '</div>'; }
    }

    public function betDetails($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE id = ?");
            $stmt->execute([$id]);
            $bet = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bet) { echo '<div class="p-10 text-center text-danger font-black uppercase italic">Scommessa non trovata.</div>'; return; }
            require __DIR__ . '/../Views/partials/modals/bet_details.php';
        } catch (\Throwable $e) { echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>'; }
    }
}
