<?php
// app/GiaNik/Controllers/GiaNikController.php

namespace App\GiaNik\Controllers;

use App\Config\Config;
use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Services\FootballApiService;
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
            $virtualBalance = 100.00; // Default

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

            $funds = $this->bf->getFunds();
            if (isset($funds['result'])) $funds = $funds['result'];

            $available = $funds['availableToBetBalance'] ?? 0;
            $exposure = abs($funds['exposure'] ?? 0);

            $balance = [
                'available_balance' => $available,
                'current_portfolio' => $available + $exposure
            ];

            // --- ENRICHMENT WITH API-FOOTBALL ---
            $apiData = null;
            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                $apiMatch = $this->findMatchingFixture($event['event'], $event['sport']);
                if ($apiMatch) {
                    $api = new FootballApiService();
                    $fixtureId = $apiMatch['fixture']['id'];
                    $stats = $api->fetchFixtureStatistics($fixtureId);
                    $events = $api->fetchFixtureEvents($fixtureId);
                    $h2h = $api->fetchH2H($apiMatch['teams']['home']['id'] . '-' . $apiMatch['teams']['away']['id']);

                    $apiData = [
                        'fixture' => $apiMatch,
                        'statistics' => $stats['response'] ?? [],
                        'events' => $events['response'] ?? [],
                        'h2h' => $h2h['response'] ?? []
                    ];
                    $event['api_football'] = $apiData;
                }
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

    private function findMatchingFixture($bfEventName, $sport)
    {
        $api = new FootballApiService();

        // 1. Try Live matches first
        $live = $api->fetchLiveMatches();
        if (!empty($live['response'])) {
            $match = $this->searchInFixtureList($bfEventName, $live['response']);
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

            if (empty($events)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessun evento live']);
                return;
            }

            // 2. Filter for Match Odds markets with volume
            $eventIds = array_map(fn($e) => $e['event']['id'], $events);
            $marketCatalogues = [];
            $chunks = array_chunk($eventIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketCatalogues($chunk, 100, ['MATCH_ODDS', 'WINNER', 'MONEYLINE']);
                if (isset($res['result'])) $marketCatalogues = array_merge($marketCatalogues, $res['result']);
            }

            $marketIds = array_map(fn($mc) => $mc['marketId'], $marketCatalogues);

            // Check which ones we already have
            if (!empty($marketIds)) {
                $placeholders = implode(',', array_fill(0, count($marketIds), '?'));
                $stmt = $this->db->prepare("SELECT market_id FROM bets WHERE market_id IN ($placeholders)");
                $stmt->execute($marketIds);
                $existingMarkets = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $existingMarkets = [];
            }

            $todo = [];
            foreach ($marketCatalogues as $mc) {
                if (!in_array($mc['marketId'], $existingMarkets)) {
                    $todo[] = $mc;
                }
            }

            // Limit to 3 concurrent analyses to avoid timeout/rate limit
            $todo = array_slice($todo, 0, 3);
            $results['scanned'] = count($todo);

            foreach ($todo as $mc) {
                try {
                    $marketId = $mc['marketId'];
                    $resBook = $this->bf->getMarketBooks([$marketId]);
                    $mb = $resBook['result'][0] ?? null;
                    if (!$mb || ($mb['totalMatched'] ?? 0) < 500) continue;

                    $event = [
                        'marketId' => $marketId,
                        'event' => $mc['event']['name'] ?? 'Unknown',
                        'competition' => $mc['competition']['name'] ?? '',
                        'sport' => $mc['eventType']['name'] ?? '',
                        'totalMatched' => $mb['totalMatched'] ?? 0,
                        'runners' => []
                    ];

                    foreach ($mb['runners'] as $r) {
                        $mR = array_filter($mc['runners'], fn($rm) => $rm['selectionId'] === $r['selectionId']);
                        $name = reset($mR)['runnerName'] ?? 'Unknown';
                        $event['runners'][] = [
                            'selectionId' => $r['selectionId'],
                            'name' => $name,
                            'back' => $r['ex']['availableToBack'][0]['price'] ?? 0
                        ];
                    }

                    // Enriched with API-Football if available
                    if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                        $apiMatch = $this->findMatchingFixture($event['event'], $event['sport']);
                        if ($apiMatch) {
                            $api = new FootballApiService();
                            $fid = $apiMatch['fixture']['id'];
                            $event['api_football'] = [
                                'statistics' => $api->fetchFixtureStatistics($fid)['response'] ?? [],
                                'events' => $api->fetchFixtureEvents($fid)['response'] ?? []
                            ];
                        }
                    }

                    $gemini = new GeminiService();
                    $predictionRaw = $gemini->analyze([$event], ['is_gianik' => true, 'available_balance' => 100, 'current_portfolio' => 100]);

                    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                        $analysis = json_decode($matches[1], true);
                        if ($analysis && !empty($analysis['advice']) && ($analysis['confidence'] ?? 0) >= 70) {
                            // Place Virtual Bet
                            $runnerName = $analysis['advice'];
                            $runners = array_map(fn($r) => ['runnerName' => $r['name'], 'selectionId' => $r['selectionId']], $event['runners']);
                            $selectionId = $this->bf->mapAdviceToSelection($runnerName, $runners);

                            if ($selectionId) {
                                $reasoning = trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));
                                $motivation = $analysis['motivation'] ?? $reasoning;

                                $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, event_name, sport, selection_id, runner_name, odds, stake, type, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmtInsert->execute([
                                    $marketId,
                                    $event['event'],
                                    $event['sport'],
                                    $selectionId,
                                    $runnerName,
                                    $analysis['odds'],
                                    2.0, // Fixed 2 euro virtual stake for auto
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

    public function recentBets()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM bets ORDER BY created_at DESC LIMIT 20");
            $stmt->execute();
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
