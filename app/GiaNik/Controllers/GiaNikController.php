<?php
// app/GiaNik/Controllers/GiaNikController.php

namespace App\GiaNik\Controllers;

use App\Config\Config;
use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Services\FootballApiService;
use App\Services\FootballDataService;
use App\Services\BasketballApiService;
use App\GiaNik\GiaNikDatabase;
use PDO;

class GiaNikController
{
    private $bf;
    private $db;
    private $footballData;

    public function __construct()
    {
        $this->bf = new BetfairService();
        $this->db = GiaNikDatabase::getInstance()->getConnection();
        $this->footballData = new FootballDataService();
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

            // 1. Get Event Types (Sports) - Restricted to Soccer (ID 1)
            $eventTypeIds = ['1'];

            // 2. Get Live Events for Soccer
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
                $targetMarkets = [
                    'MATCH_ODDS',
                    'DOUBLE_CHANCE',
                    'DRAW_NO_BET',
                    'HALF_TIME',
                    'OVER_UNDER_05',
                    'OVER_UNDER_15',
                    'OVER_UNDER_25',
                    'OVER_UNDER_35',
                    'OVER_UNDER_45',
                    'BOTH_TEAMS_TO_SCORE'
                ];
                $res = $this->bf->getMarketCatalogues($chunk, 100, $targetMarkets);
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
            $apiLiveRes = $this->footballData->getLiveMatches();
            $apiLiveMatches = $apiLiveRes['response'] ?? [];

            // 5. Merge Data
            $marketBooksMap = [];
            foreach ($marketBooks as $mb) {
                $marketBooksMap[$mb['marketId']] = $mb;
            }

            // Prioritize market types (Match Odds > Winner > Moneyline)
            usort($marketCatalogues, function ($a, $b) {
                $prio = ['MATCH_ODDS' => 1, 'WINNER' => 2, 'MONEYLINE' => 3];
                $typeA = $a['description']['marketType'] ?? '';
                $typeB = $b['description']['marketType'] ?? '';
                return ($prio[$typeA] ?? 99) <=> ($prio[$typeB] ?? 99);
            });

            $groupedMatches = [];
            $processedEvents = [];
            foreach ($marketCatalogues as $mc) {
                $marketId = $mc['marketId'];
                $eventId = $mc['event']['id'];

                if (isset($processedEvents[$eventId]))
                    continue;
                if (!isset($marketBooksMap[$marketId]))
                    continue;

                $processedEvents[$eventId] = true;
                $mb = $marketBooksMap[$marketId];
                $sport = 'Soccer'; // Hardcoded as we only fetch eventType 1

                $m = [
                    'marketId' => $marketId,
                    'event' => $mc['event']['name'],
                    'event_id' => $mc['event']['id'],
                    'fixture_id' => null,
                    'home_id' => null,
                    'away_id' => null,
                    'competition' => $mc['competition']['name'] ?? '',
                    'sport' => $sport,
                    'totalMatched' => $mb['totalMatched'] ?? 0,
                    'runners' => [],
                    'status_label' => 'LIVE',
                    'score' => null,
                    'has_api_data' => false,
                    'home_logo' => null,
                    'away_logo' => null,
                    'home_name' => null,
                    'away_name' => null,
                    'country' => $mc['event']['countryCode'] ?? null,
                    'flag' => null
                ];

                // --- Enrichment ---
                $foundApiData = false;
                if (true) { // Always soccer now
                    $match = $this->footballData->searchInFixtureList($m['event'], $apiLiveMatches);
                    if ($match) {
                        $m['fixture_id'] = $match['fixture']['id'] ?? null;
                        $m['home_id'] = $match['teams']['home']['id'] ?? null;
                        $m['away_id'] = $match['teams']['away']['id'] ?? null;
                        $m['score'] = ($match['goals']['home'] ?? '0') . '-' . ($match['goals']['away'] ?? '0');
                        $elapsed = $match['fixture']['status']['elapsed'] ?? 0;
                        $m['status_label'] = ($match['fixture']['status']['short'] ?? 'LIVE') . ($elapsed ? " $elapsed'" : "");
                        $m['has_api_data'] = true;
                        $m['home_logo'] = $match['teams']['home']['logo'] ?? null;
                        $m['away_logo'] = $match['teams']['away']['logo'] ?? null;
                        $m['home_name'] = $match['teams']['home']['name'] ?? null;
                        $m['away_name'] = $match['teams']['away']['name'] ?? null;
                        $m['country'] = $match['league']['country'] ?? $m['country'];
                        $m['flag'] = $match['league']['flag'] ?? null;
                        $foundApiData = true;
                    }
                }

                // If no API data, try to split the event name for UI purposes
                if (!$foundApiData) {
                    $teams = preg_split('/\s+(v|vs|@)\s+/i', $m['event']);
                    if (count($teams) >= 2) {
                        $m['home_name'] = trim($teams[0]);
                        $m['away_name'] = trim($teams[1]);
                    } else {
                        $m['home_name'] = $m['event'];
                        $m['away_name'] = '';
                    }
                }

                // Generic Betfair-based enrichment (fallback or additional info)
                if (!$foundApiData && isset($mb['marketDefinition'])) {
                    $def = $mb['marketDefinition'];

                    // 1. Try to get score from marketDefinition['score'] (Reliable for Tennis, Volley, etc)
                    if (isset($def['score'])) {
                        $s = $def['score'];
                        // Tennis sets/games style
                        if (isset($s['homeSets']) || isset($s['awaySets']) || isset($s['homeGames'])) {
                            $hs = $s['homeSets'] ?? 0;
                            $as = $s['awaySets'] ?? 0;
                            $m['score'] = "$hs-$as";
                            if (isset($s['homeGames'], $s['awayGames'])) {
                                $m['score'] .= " (" . $s['homeGames'] . "-" . $s['awayGames'] . ")";
                            }
                            if ($m['status_label'] === 'LIVE')
                                $m['status_label'] = 'SET';
                        }
                        // Generic homeScore/awayScore
                        elseif (isset($s['homeScore'], $s['awayScore'])) {
                            if (!$m['score'])
                                $m['score'] = $s['homeScore'] . '-' . $s['awayScore'];
                        }
                    }

                    // 2. Score check in runner names (sometimes present in specific markets)
                    if (!$m['score']) {
                        foreach ($mb['runners'] as $runner) {
                            if (isset($runner['description']['runnerName']) && preg_match('/(\d+)\s*-\s*(\d+)/', $runner['description']['runnerName'], $runnerScore)) {
                                // $m['score'] = $runnerScore[1] . '-' . $runnerScore[2]; // Rischioso prenderlo dai runner
                            }
                        }
                    }

                    // 3. Fallback: Extract scores from event name if present (e.g. "Team A 1-0 Team B")
                    if (!$m['score'] && preg_match('/(\d+)\s*[-]\s*(\d+)/', $m['event'], $scoreMatches)) {
                        $m['score'] = $scoreMatches[1] . '-' . $scoreMatches[2];
                    }

                    // 4. Status label refinements
                    if (preg_match('/\((Q[1-4]|Set\s*[1-5]|HT|End\s*Set\s*\d)\)/i', $m['event'], $periodMatches)) {
                        $m['status_label'] = strtoupper($periodMatches[1]);
                    } elseif (isset($def['inPlay']) && $def['inPlay'] && $m['status_label'] === 'LIVE') {
                        $m['status_label'] = 'LIVE';
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

            uksort($groupedMatches, function ($a, $b) use ($groupedMatches) {
                return count($groupedMatches[$b]) <=> count($groupedMatches[$a]);
            });

            // Funds
            $account = ['available' => 0, 'exposure' => 0];
            $funds = $this->bf->getFunds();
            if (isset($funds['result']))
                $funds = $funds['result'];
            $account['available'] = $funds['availableToBetBalance'] ?? 0;
            $account['exposure'] = abs($funds['exposure'] ?? 0);

            // Virtual
            $virtualAccount = $this->getVirtualBalance();

            $this->settleBets();
            require __DIR__ . '/../Views/partials/gianik_live.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore GiaNik Live: ' . $e->getMessage() . '</div>';
        }
    }

    public function analyze($marketId)
    {
        set_time_limit(60); // Aumenta timeout per l'AI
        try {
            $resCat = $this->bf->request('listMarketCatalogue', [
                'filter' => ['marketIds' => [$marketId]],
                'maxResults' => 1,
                'marketProjection' => ['EVENT', 'COMPETITION', 'EVENT_TYPE', 'RUNNER_DESCRIPTION', 'MARKET_DESCRIPTION']
            ]);
            $initialMc = $resCat['result'][0] ?? null;

            if (!$initialMc) {
                $reasoning = "Evento non trovato o mercato non più attivo su Betfair.";
                require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
                return;
            }

            $eventId = $initialMc['event']['id'];
            $eventName = $initialMc['event']['name'];
            $competitionName = $initialMc['competition']['name'] ?? '';
            $sportName = $initialMc['eventType']['name'] ?? '';

            $marketTypes = [
                'MATCH_ODDS',
                'OVER_UNDER_05',
                'OVER_UNDER_15',
                'OVER_UNDER_25',
                'OVER_UNDER_35',
                'OVER_UNDER_45',
                'BOTH_TEAMS_TO_SCORE',
                'DOUBLE_CHANCE',
                'DRAW_NO_BET',
                'HALF_TIME',
                'HALF_TIME_FULL_TIME',
                'MATCH_ODDS_AND_OU_25',
                'MATCH_ODDS_AND_BTTS'
            ];

            $allMcRes = $this->bf->getMarketCatalogues([$eventId], 20, $marketTypes);
            $catalogues = $allMcRes['result'] ?? [$initialMc];

            $marketIds = array_map(fn($mc) => $mc['marketId'], $catalogues);
            $booksRes = $this->bf->getMarketBooks($marketIds);
            $booksMap = [];
            foreach ($booksRes['result'] ?? [] as $b)
                $booksMap[$b['marketId']] = $b;

            $event = [
                'event' => $eventName,
                'competition' => $competitionName,
                'sport' => $sportName,
                'markets' => []
            ];

            foreach ($catalogues as $mc) {
                $mId = $mc['marketId'];
                if (!isset($booksMap[$mId]))
                    continue;
                $book = $booksMap[$mId];
                if (($book['status'] ?? '') !== 'OPEN')
                    continue;

                $m = [
                    'marketId' => $mId,
                    'marketName' => $mc['marketName'] ?? 'Unknown',
                    'totalMatched' => (float) ($book['totalMatched'] ?? 0),
                    'runners' => []
                ];
                foreach ($book['runners'] as $r) {
                    $mR = array_filter($mc['runners'], fn($rm) => $rm['selectionId'] === $r['selectionId']);
                    $name = reset($mR)['runnerName'] ?? 'Unknown';

                    // Prendi i migliori prezzi Back e Lay
                    $backPrice = $r['ex']['availableToBack'][0]['price'] ?? 0;
                    $layPrice = $r['ex']['availableToLay'][0]['price'] ?? 0;

                    // Se non ci sono prezzi disponibili, prova a usare l'ultimo prezzo scambiato
                    if ($backPrice == 0 && isset($r['lastPriceTraded'])) {
                        $backPrice = $r['lastPriceTraded'];
                    }

                    $m['runners'][] = [
                        'selectionId' => $r['selectionId'],
                        'name' => $name,
                        'back' => (float) $backPrice,
                        'lay' => (float) $layPrice
                    ];
                }
                $event['markets'][] = $m;
            }

            $vBalance = $this->getVirtualBalance();
            $balance = ['available_balance' => $vBalance['available'], 'current_portfolio' => $vBalance['total']];

            // Aggiungiamo il marketId di partenza come suggerimento per l'AI
            $event['requestedMarketId'] = $marketId;

            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition']);
            }

            $gemini = new GeminiService();

            // Debug $event
            file_put_contents(Config::LOGS_PATH . 'gianik_event_debug.log', date('[Y-m-d H:i:s] ') . json_encode($event, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

            $predictionRaw = $gemini->analyze([$event], array_merge($balance, ['is_gianik' => true]));

            $analysis = [];
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                $analysis = json_decode($matches[1], true);
            }
            $reasoning = trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));

            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
        } catch (\Throwable $e) {
            $reasoning = "Si è verificato un errore durante l'analisi: " . $e->getMessage();
            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
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
            $stake = (float) ($input['stake'] ?? 2.0);
            if ($stake < 2.0)
                $stake = 2.0;
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
            // Restricted to Soccer (ID 1)
            $eventTypeIds = ['1'];
            $liveEventsRes = $this->bf->getLiveEvents($eventTypeIds);
            $events = $liveEventsRes['result'] ?? [];

            $apiLiveRes = $this->footballData->getLiveMatches();
            $apiLiveFixtures = $apiLiveRes['response'] ?? [];

            if (empty($events)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessun evento live']);
                return;
            }

            $eventIds = array_map(fn($e) => $e['event']['id'], $events);
            $marketTypes = [
                'MATCH_ODDS',
                'OVER_UNDER_05',
                'OVER_UNDER_15',
                'OVER_UNDER_25',
                'OVER_UNDER_35',
                'OVER_UNDER_45',
                'BOTH_TEAMS_TO_SCORE',
                'DOUBLE_CHANCE',
                'DRAW_NO_BET',
                'HALF_TIME',
                'HALF_TIME_FULL_TIME',
                'MATCH_ODDS_AND_OU_25',
                'MATCH_ODDS_AND_BTTS'
            ];

            $marketCatalogues = [];
            $chunks = array_chunk($eventIds, 40);
            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketCatalogues($chunk, 200, $marketTypes);
                if (isset($res['result']))
                    $marketCatalogues = array_merge($marketCatalogues, $res['result']);
            }

            $eventMarketsMap = [];
            foreach ($marketCatalogues as $mc) {
                $eid = $mc['event']['id'];
                $eventMarketsMap[$eid][] = $mc;
            }

            $stmtPending = $this->db->prepare("SELECT DISTINCT event_name FROM bets WHERE status = 'pending'");
            $stmtPending->execute();
            $pendingEventNames = $stmtPending->fetchAll(PDO::FETCH_COLUMN);

            // Recuperiamo il conteggio delle scommesse per oggi per ogni match (per limitare ingressi multipli)
            $stmtCount = $this->db->prepare("SELECT event_name, COUNT(*) as cnt FROM bets WHERE created_at >= date('now') GROUP BY event_name");
            $stmtCount->execute();
            $matchBetCounts = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR);

            $eventCounter = 0;
            foreach ($eventMarketsMap as $eid => $catalogues) {
                if ($eventCounter >= 3)
                    break;
                $mainEvent = $catalogues[0];
                if (in_array($mainEvent['event']['name'], $pendingEventNames))
                    continue;

                try {
                    $results['scanned']++;
                    $eventCounter++;

                    $marketIds = array_map(fn($mc) => $mc['marketId'], $catalogues);
                    $booksRes = $this->bf->getMarketBooks($marketIds);
                    $booksMap = [];
                    foreach ($booksRes['result'] ?? [] as $b)
                        $booksMap[$b['marketId']] = $b;

                    $event = [
                        'event' => $mainEvent['event']['name'],
                        'competition' => $mainEvent['competition']['name'] ?? '',
                        'sport' => $mainEvent['eventType']['name'] ?? '',
                        'markets' => []
                    ];

                    foreach ($catalogues as $mc) {
                        $mId = $mc['marketId'];
                        if (!isset($booksMap[$mId]))
                            continue;
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
                    }

                    $vBalance = $this->getVirtualBalance();

                    $gemini = new GeminiService();
                    $predictionRaw = $gemini->analyze([$event], [
                        'is_gianik' => true,
                        'available_balance' => $vBalance['available'],
                        'current_portfolio' => $vBalance['total']
                    ]);

                    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                        $analysis = json_decode($matches[1], true);
                        if ($analysis && !empty($analysis['marketId']) && !empty($analysis['advice']) && ($analysis['confidence'] ?? 0) >= 80) {

                            // Controllo limite 4 scommesse per match (Calcio)
                            $currentCount = $matchBetCounts[$event['event']] ?? 0;
                            if (($event['sport'] === 'Soccer' || $event['sport'] === 'Football') && $currentCount >= 4) {
                                continue;
                            }
                            $selectedMarket = null;
                            foreach ($event['markets'] as $m) {
                                if ($m['marketId'] === $analysis['marketId']) {
                                    $selectedMarket = $m;
                                    break;
                                }
                            }
                            if (!$selectedMarket)
                                continue;

                            $stake = (float) ($analysis['stake'] ?? 2.0);
                            if ($stake < 2.0)
                                $stake = 2.0;
                            $vBalance = $this->getVirtualBalance();
                            if ($vBalance['available'] < $stake)
                                continue;

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
                } catch (\Throwable $ex) {
                    $results['errors'][] = $ex->getMessage();
                }
            }
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
            foreach ($pending as $bet) {
                $res = $this->bf->getMarketBooks([$bet['market_id']]);
                $mb = $res['result'][0] ?? null;
                if ($mb && $mb['status'] === 'CLOSED') {
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
                        $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $profit, $bet['id']]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("GiaNik Settlement Error: " . $e->getMessage());
        }
    }

    private function enrichWithApiData($bfEventName, $sport, $preFetchedLive = null, $competition = '')
    {
        // Restricted to Soccer
        $apiMatch = $this->findMatchingFixture($bfEventName, $sport, $preFetchedLive);
        if (!$apiMatch)
            return null;

        $fid = $apiMatch['fixture']['id'];
        $details = $this->footballData->getFixtureDetails($fid);
        $status = $details['status_short'] ?? 'NS';

        $h2h = $this->footballData->getH2H($apiMatch['teams']['home']['id'], $apiMatch['teams']['away']['id']);
        $standings = (isset($apiMatch['league']['id'], $apiMatch['league']['season'])) ? $this->footballData->getStandings($apiMatch['league']['id'], $apiMatch['league']['season']) : null;
        $preds = $this->footballData->getFixturePredictions($fid, $status);

        // 🎯 STATISTICS LIVE (possesso palla, tiri, corner, ecc.)
        $stats = $this->footballData->getFixtureStatistics($fid, $status);

        // ⚽ EVENTS LIVE (gol, cartellini, sostituzioni, VAR)
        $events = $this->footballData->getFixtureEvents($fid, $status);

        // 🚨 DATI LIVE ESPLICITI per Gemini
        $liveData = [
            'live_score' => [
                'home' => $details['score_home'] ?? 0,
                'away' => $details['score_away'] ?? 0,
                'halftime_home' => $details['score_home_ht'] ?? null,
                'halftime_away' => $details['score_away_ht'] ?? null
            ],
            'live_status' => [
                'short' => $details['status_short'] ?? 'NS',
                'long' => $details['status_long'] ?? 'Not Started',
                'elapsed_minutes' => $details['elapsed'] ?? 0
            ],
            'match_info' => [
                'fixture_id' => $fid,
                'date' => $details['date'] ?? null,
                'venue_id' => $details['venue_id'] ?? null
            ]
        ];

        return [
            'fixture' => $details,
            'live' => $liveData,  // ← DATI LIVE ESPLICITI
            'statistics' => $stats,  // ← STATISTICS LIVE (shots, possession, corners, ecc.)
            'events' => $events,  // ← EVENTS LIVE (gol, cards, subs, VAR)
            'h2h' => $h2h['h2h_json'] ?? [],
            'standings' => $standings,
            'predictions' => $preds['prediction_json'] ?? null
        ];
    }

    private function findMatchingFixture($bfEventName, $sport, $preFetchedLive = null)
    {
        $liveFixtures = $preFetchedLive ?? $this->footballData->getLiveMatches()['response'] ?? [];
        return $this->footballData->searchInFixtureList($bfEventName, $liveFixtures);
    }

    public function recentBets()
    {
        try {
            $statusFilter = $_GET['status'] ?? 'all';

            // 1. Sport mapping (only soccer needed now but kept for consistency)
            $sportMapping = [
                'Soccer' => 'Calcio',
                'Football' => 'Calcio'
            ];

            // 2. Main query for bets
            $sql = "SELECT * FROM bets";
            $where = [];
            $params = [];

            // Restricted to Soccer/Football
            $where[] = "sport IN ('Soccer', 'Football')";

            if ($statusFilter === 'won') {
                $where[] = "status = 'won'";
            } elseif ($statusFilter === 'lost') {
                $where[] = "status = 'lost'";
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " ORDER BY created_at DESC LIMIT 20";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Map sport names for bets
            foreach ($bets as &$bet) {
                $bet['sport_it'] = $sportMapping[$bet['sport']] ?? 'Calcio';
            }

            // Pass filters to the view
            $currentStatus = $statusFilter;
            $currentSport = 'all';
            $sports = []; // Dropdown removed from view

            require __DIR__ . '/../Views/partials/recent_bets_sidebar.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-2 text-[10px]">' . $e->getMessage() . '</div>';
        }
    }

    private function getVirtualBalance()
    {
        $vInit = 100.0;
        $vProf = (float) $this->db->query("SELECT SUM(profit) FROM bets WHERE status IN ('won', 'lost') AND sport IN ('Soccer', 'Football')")->fetchColumn();
        $vExp = (float) $this->db->query("SELECT SUM(stake) FROM bets WHERE status = 'pending' AND sport IN ('Soccer', 'Football')")->fetchColumn();
        return [
            'available' => ($vInit + $vProf) - $vExp,
            'exposure' => $vExp,
            'total' => $vInit + $vProf
        ];
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

    public function predictions($fixtureId)
    {
        try {
            $details = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $details['status_short'] ?? 'NS';
            $predictionsData = $this->footballData->getFixturePredictions($fixtureId, $statusShort);

            if (!$predictionsData) {
                echo '<div class="p-10 text-center text-danger font-black uppercase italic">Pronostico non disponibile.</div>';
                return;
            }

            $prediction = $predictionsData['prediction_json'];
            $comparison = $predictionsData['comparison_json'];

            require __DIR__ . '/../Views/partials/modals/prediction_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function matchDetails($fixtureId)
    {
        try {
            $details = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $details['status_short'] ?? 'NS';

            $events = $this->footballData->getFixtureEvents($fixtureId, $statusShort);
            $stats = $this->footballData->getFixtureStatistics($fixtureId, $statusShort);
            $predictionsData = $this->footballData->getFixturePredictions($fixtureId, $statusShort);
            $predictions = $predictionsData['prediction_json'] ?? null;

            require __DIR__ . '/../Views/partials/match_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger text-[10px]">Error loading details: ' . $e->getMessage() . '</div>';
        }
    }

    public function teamDetails($teamId)
    {
        try {
            $teamData = $this->footballData->getTeamDetails($teamId);
            if (!$teamData) {
                echo '<div class="p-10 text-center text-danger">Team non trovato.</div>';
                return;
            }
            // Mocking structure for view if needed
            $team = ['team' => $teamData, 'venue' => $teamData]; // Team model getById returns merged data
            require __DIR__ . '/../Views/partials/modals/team_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function playerDetails($playerId, $fixtureId)
    {
        try {
            $details = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $details['status_short'] ?? 'NS';
            $playersStats = $this->footballData->getFixturePlayerStatistics($fixtureId, $statusShort);
            $events = $this->footballData->getFixtureEvents($fixtureId, $statusShort);

            $player = null;
            foreach ($playersStats as $row) {
                if ($row['player_id'] == $playerId) {
                    $player = $row['stats_json'];
                    $player['team'] = ['id' => $row['team_id'], 'name' => $row['team_name'], 'logo' => $row['team_logo']];
                    break;
                }
            }

            if (!$player) {
                $playerData = $this->footballData->getPlayer($playerId);
                if ($playerData) {
                    $stats = (new \App\Models\PlayerStatistics())->get($playerId, \App\Config\Config::getCurrentSeason());
                    $player = [
                        'player' => $playerData,
                        'statistics' => $stats ? json_decode($stats['stats_json'], true) : []
                    ];
                }
            }

            if (!$player) {
                echo '<div class="p-10 text-center text-danger">Giocatore non trovato.</div>';
                return;
            }

            // CROSS-CHECK with Events to avoid data latency issues
            $eventGoals = 0;
            foreach ($events as $ev) {
                if ($ev['player']['id'] == $playerId && strtolower($ev['type']) === 'goal') {
                    $eventGoals++;
                }
            }

            // If events show more goals than stats, trust the events
            if (isset($player['statistics'][0])) {
                if (($player['statistics'][0]['goals']['total'] ?? 0) < $eventGoals) {
                    $player['statistics'][0]['goals']['total'] = $eventGoals;
                }

                // Also ensure minutes is at least something if he scored
                if ($eventGoals > 0 && ($player['statistics'][0]['games']['minutes'] ?? 0) === 0) {
                    // Try to guess minutes from last event or just set a placeholder
                    $player['statistics'][0]['games']['minutes'] = 'Live';
                }
            }

            require __DIR__ . '/../Views/partials/modals/player_stats.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }
}
