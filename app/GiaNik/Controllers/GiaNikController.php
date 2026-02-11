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

            // --- Auto-Sync with Betfair ---
            $this->syncWithBetfair();

            // 1. Get Event Types (Sports) - Restricted to Soccer (ID 1)
            $eventTypeIds = ['1'];

            // 2. Get Upcoming Events for Soccer (Next 24h)
            $liveEventsRes = $this->bf->getUpcomingEvents($eventTypeIds, 24);
            $events = $liveEventsRes['result'] ?? [];

            if (empty($events)) {
                echo '<div class="text-center py-10"><span class="text-slate-500 text-sm font-bold uppercase">Nessun evento nelle prossime 24 ore su Betfair</span></div>';
                return;
            }

            $eventIds = array_map(fn($e) => $e['event']['id'], $events);
            $eventStartTimes = [];
            foreach ($events as $e) {
                $eventStartTimes[$e['event']['id']] = $e['event']['openDate'] ?? null;
            }

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
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            // Fetch Live with short cache (15s) for high reactivity in GiaNik
            $apiLiveRes = $this->footballData->getLiveMatches([], 15);
            $apiTodayRes = $this->footballData->getFixturesByDate($today);
            $apiTomorrowRes = $this->footballData->getFixturesByDate($tomorrow);

            // Merge order: Today/Tomorrow first, then Live.
            // This ensures Live data (most fresh) overwrites any stale Today/Tomorrow cached records in the map.
            $allApiFixtures = array_merge(
                $apiTodayRes['response'] ?? [],
                $apiTomorrowRes['response'] ?? [],
                $apiLiveRes['response'] ?? []
            );
            // Deduplicate by fixture ID
            $apiLiveMatchesMap = [];
            foreach ($allApiFixtures as $f) {
                if (isset($f['fixture']['id'])) {
                    $apiLiveMatchesMap[$f['fixture']['id']] = $f;
                }
            }
            $apiLiveMatches = array_values($apiLiveMatchesMap);

            // --- Truth Overlay from Local DB (for most up-to-date individual status) ---
            $fixtureModel = new \App\Models\Fixture();
            $dbFixtures = $fixtureModel->getActiveFixtures();
            $dbFixturesMap = [];
            foreach ($dbFixtures as $f) {
                $dbFixturesMap[$f['id']] = $f;
            }

            // --- P&L Tracking for Real Bets ---
            $stmtBets = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND type = 'real'");
            $stmtBets->execute();
            $activeRealBets = $stmtBets->fetchAll(PDO::FETCH_ASSOC);

            // --- Score Tracking for Highlighting ---
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $prevScores = $_SESSION['gianik_scores'] ?? [];
            $newScores = [];

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

                $startTime = $eventStartTimes[$eventId] ?? null;
                $statusLabel = 'LIVE';
                if ($startTime) {
                    $odt = new \DateTime($startTime);
                    $odt->setTimezone(new \DateTimeZone('Europe/Rome'));
                    $now = new \DateTime();
                    if ($odt > $now && !($mb['marketDefinition']['inPlay'] ?? false)) {
                        if ($odt->format('Y-m-d') === $now->format('Y-m-d')) {
                            $statusLabel = $odt->format('H:i');
                        } else {
                            $statusLabel = $odt->format('d/m H:i');
                        }
                    }
                }

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
                    'status_label' => $statusLabel,
                    'start_time' => $startTime,
                    'score' => null,
                    'has_api_data' => false,
                    'home_logo' => null,
                    'away_logo' => null,
                    'home_name' => null,
                    'away_name' => null,
                    'country' => $mc['event']['countryCode'] ?? null,
                    'flag' => null,
                    'is_in_play' => $mb['marketDefinition']['inPlay'] ?? false
                ];

                // --- Enrichment ---
                $foundApiData = false;
                if (true) { // Always soccer now
                    $countryCode = $m['country'];
                    $mappedCountry = $this->getCountryMapping($countryCode);

                    // Handle international/continental competitions
                    $comp = strtolower($m['competition']);
                    if (
                        strpos($comp, 'champions league') !== false ||
                        strpos($comp, 'europa league') !== false ||
                        strpos($comp, 'conference league') !== false ||
                        strpos($comp, 'friendly') !== false ||
                        strpos($comp, 'cup') !== false ||
                        strpos($comp, 'international') !== false ||
                        strpos($comp, 'world cup') !== false ||
                        strpos($comp, 'euro ') !== false ||
                        strpos($comp, 'copa america') !== false
                    ) {
                        if ($mappedCountry) {
                            $mappedCountry = is_array($mappedCountry) ? $mappedCountry : [$mappedCountry];
                            $mappedCountry[] = 'World';
                        } else {
                            $mappedCountry = 'World';
                        }
                    }

                    $match = $this->footballData->searchInFixtureList($m['event'], $apiLiveMatches, $mappedCountry);

                    if ($match) {
                        $fid = $match['fixture']['id'] ?? null;
                        $m['fixture_id'] = $fid;
                        $m['home_id'] = $match['teams']['home']['id'] ?? null;
                        $m['away_id'] = $match['teams']['away']['id'] ?? null;

                        $scoreHome = $match['goals']['home'] ?? '0';
                        $scoreAway = $match['goals']['away'] ?? '0';
                        $statusShort = $match['fixture']['status']['short'] ?? 'LIVE';
                        $elapsed = $match['fixture']['status']['elapsed'] ?? 0;

                        // Overlay with DB data if we have it and it's newer or more detailed
                        if ($fid && isset($dbFixturesMap[$fid])) {
                            $dbf = $dbFixturesMap[$fid];
                            // If DB has a higher elapsed time or a different (more advanced) status, use it
                            if ($dbf['elapsed'] > $elapsed || in_array($dbf['status_short'], ['FT', 'AET', 'PEN'])) {
                                $scoreHome = $dbf['score_home'];
                                $scoreAway = $dbf['score_away'];
                                $statusShort = $dbf['status_short'];
                                $elapsed = $dbf['elapsed'];
                            }
                        }

                        $m['score'] = "$scoreHome-$scoreAway";

                        // If match is NOT started (NS, TBD) or it's just 'LIVE' with 0 elapsed (scheduled to start), 
                        // keep the Betfair start time instead of the status tag.
                        if (in_array($statusShort, ['NS', 'TBD'])) {
                            // Keep the time label we already have
                        } else {
                            $m['status_label'] = $statusShort . ($elapsed ? " $elapsed'" : "");
                        }
                        $m['elapsed'] = $elapsed;
                        $m['status_short'] = $statusShort;
                        $m['has_api_data'] = true;
                        $m['home_logo'] = $match['teams']['home']['logo'] ?? null;
                        $m['away_logo'] = $match['teams']['away']['logo'] ?? null;
                        $m['home_name'] = $match['teams']['home']['name'] ?? null;
                        $m['away_name'] = $match['teams']['away']['name'] ?? null;
                        $m['country'] = $match['league']['country'] ?? $m['country'];
                        $m['flag'] = $match['league']['flag'] ?? null;
                        $m['league_id'] = $match['league']['id'] ?? null;
                        $m['season'] = $match['league']['season'] ?? null;
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

            // Flatten and Sort matches dynamically
            $allMatches = [];
            foreach ($groupedMatches as $sportMatches) {
                foreach ($sportMatches as $m) {
                    $m['has_active_real_bet'] = false;
                    $m['current_pl'] = 0;
                    $m['just_updated'] = false;

                    $m['has_active_virtual_bet'] = false;

                    // Calculate P&L for real bets
                    foreach ($activeRealBets as $bet) {
                        if ($bet['market_id'] === $m['marketId']) {
                            $m['has_active_real_bet'] = true;
                            // Find current Back odds for this runner
                            $currentOdds = 0;
                            foreach ($m['runners'] as $r) {
                                if ($r['selectionId'] == $bet['selection_id']) {
                                    $currentOdds = is_numeric($r['back']) ? (float) $r['back'] : 0;
                                    break;
                                }
                            }
                            if ($currentOdds > 1.0) {
                                // Estimated Cashout: (Placed Odds / Current Odds) * Stake - Stake
                                $m['current_pl'] += (($bet['odds'] / $currentOdds) * $bet['stake']) - $bet['stake'];
                            }
                        }
                    }

                    // Check for virtual bets
                    $stmtV = $this->db->prepare("SELECT id FROM bets WHERE market_id = ? AND type = 'virtual' AND status = 'pending'");
                    $stmtV->execute([$m['marketId']]);
                    if ($stmtV->fetch()) {
                        $m['has_active_virtual_bet'] = true;
                    }

                    // Detect Score Changes
                    $scoreKey = $m['event_id'];
                    $currentScore = $m['score'] ?? '0-0';
                    $newScores[$scoreKey] = $currentScore;
                    if (isset($prevScores[$scoreKey]) && $prevScores[$scoreKey] !== $currentScore) {
                        $m['just_updated'] = time();
                    }

                    $allMatches[] = $m;
                }
            }
            $_SESSION['gianik_scores'] = $newScores;

            // Sort logic: 1. Real Bets (P&L DESC), 2. Virtual Bets, 3. Live (Elapsed DESC), 4. Upcoming (Start ASC)
            usort($allMatches, function ($a, $b) {
                // Priority 1: Active Real Bets
                if ($a['has_active_real_bet'] && !$b['has_active_real_bet'])
                    return -1;
                if (!$a['has_active_real_bet'] && $b['has_active_real_bet'])
                    return 1;
                if ($a['has_active_real_bet'] && $b['has_active_real_bet']) {
                    // Sort by absolute P&L value to put the most "active" positions first
                    return abs($b['current_pl']) <=> abs($a['current_pl']);
                }

                // Priority 2: Active Virtual Bets
                if (($a['has_active_virtual_bet'] ?? false) && !($b['has_active_virtual_bet'] ?? false))
                    return -1;
                if (!($a['has_active_virtual_bet'] ?? false) && ($b['has_active_virtual_bet'] ?? false))
                    return 1;

                // Priority 3: Is Live (In Play)
                if ($a['is_in_play'] && !$b['is_in_play'])
                    return -1;
                if (!$a['is_in_play'] && $b['is_in_play'])
                    return 1;
                if ($a['is_in_play'] && $b['is_in_play']) {
                    // Among live matches, sort by elapsed time descending (ending soonest first)
                    $aElapsed = $a['elapsed'] ?? 0;
                    $bElapsed = $b['elapsed'] ?? 0;
                    if ($aElapsed !== $bElapsed)
                        return $bElapsed <=> $aElapsed;
                }

                // Priority 4: Upcoming Matches (Start Time)
                if (!$a['is_in_play'] && !$b['is_in_play']) {
                    $aStart = $a['start_time'] ?? '9999-99-99';
                    $bStart = $b['start_time'] ?? '9999-99-99';
                    if ($aStart !== $bStart)
                        return $aStart <=> $bStart;
                }

                // Priority 5: Matched Volume
                return ($b['totalMatched'] ?? 0) <=> ($a['totalMatched'] ?? 0);
            });

            // Re-group for view compatibility if needed, but the view expects $allMatches now
            // Wait, the view was iterating over $groupedMatches? No, I'll update the view too.

            // Funds
            $account = ['available' => 0, 'exposure' => 0];
            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            if (isset($funds['availableToBetBalance'])) {
                $account['available'] = $funds['availableToBetBalance'] ?? 0;
                $account['exposure'] = abs($funds['exposure'] ?? 0);
            }

            // Operational mode is now forced to real
            $operationalMode = 'real';

            // Balance is already fetched above in $account from real Betfair

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

            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            $balance = [
                'available_balance' => (float) ($funds['availableToBetBalance'] ?? 0),
                'current_portfolio' => (float) ($funds['availableToBetBalance'] ?? 0) + abs((float) ($funds['exposure'] ?? 0))
            ];

            // Aggiungiamo il marketId di partenza come suggerimento per l'AI
            $event['requestedMarketId'] = $marketId;

            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                // Pass the initial market book for fallback extraction
                $mainBook = $booksMap[$marketId] ?? null;
                $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition'], $mainBook);
            }

            $gemini = new GeminiService();

            // Debug $event
            file_put_contents(Config::LOGS_PATH . 'gianik_event_debug.log', date('[Y-m-d H:i:s] ') . json_encode($event, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

            $predictionRaw = $gemini->analyze([$event], array_merge($balance, ['is_gianik' => true]));

            $analysis = [];
            $jsonContent = '';
            if (preg_match('/```json\s*([\s\S]*?)(?:```|$)/', $predictionRaw, $matches)) {
                $jsonContent = trim($matches[1]);
                $analysis = json_decode($jsonContent, true);

                // Fallback parsing if json_decode fails (e.g. truncated)
                if ($analysis === null && !empty($jsonContent)) {
                    // Try to fix missing closing braces
                    $bracesOpen = substr_count($jsonContent, '{');
                    $bracesClose = substr_count($jsonContent, '}');
                    if ($bracesOpen > $bracesClose) {
                        $jsonContent .= str_repeat('}', $bracesOpen - $bracesClose);
                        $analysis = json_decode($jsonContent, true);
                    }
                }
            }
            $reasoning = trim(preg_replace('/```json[\s\S]*?(?:```|$)/', '', $predictionRaw));

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
            $type = 'real'; // Forced to real
            $eventName = $input['eventName'] ?? 'Unknown';
            $sport = $input['sport'] ?? 'Unknown';
            $runnerName = $input['runnerName'] ?? 'Unknown';
            $motivation = $input['motivation'] ?? '';

            if (!$marketId || !$selectionId || !$odds) {
                echo json_encode(['status' => 'error', 'message' => 'Dati mancanti']);
                return;
            }

            // Enforce minimum odds as per system rule
            if ($odds < Config::MIN_BETFAIR_ODDS) {
                $odds = Config::MIN_BETFAIR_ODDS;
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

    public function setMode()
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $mode = $input['mode'] ?? 'virtual';

            $stmt = $this->db->prepare("INSERT INTO system_state (key, value, updated_at) VALUES ('operational_mode', ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$mode]);

            echo json_encode(['status' => 'success', 'mode' => $mode]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getMode()
    {
        header('Content-Type: application/json');
        $stmt = $this->db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
        $stmt->execute();
        $mode = $stmt->fetchColumn() ?: 'virtual';
        echo json_encode(['status' => 'success', 'mode' => $mode]);
    }

    public function autoProcess()
    {
        header('Content-Type: application/json');
        $results = ['scanned' => 0, 'new_bets' => 0, 'errors' => []];
        try {
            // Sincronizza lo stato reale prima di procedere
            $this->syncWithBetfair();

            // Force real mode
            $globalMode = 'real';

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

            $stmtPending = $this->db->prepare("SELECT DISTINCT event_name, market_id FROM bets WHERE status = 'pending'");
            $stmtPending->execute();
            $pendingBetsRaw = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
            $pendingEventNames = array_column($pendingBetsRaw, 'event_name');
            $pendingMarketIds = array_column($pendingBetsRaw, 'market_id');

            // Recuperiamo il conteggio delle scommesse per oggi per ogni match (per limitare ingressi multipli)
            $stmtCount = $this->db->prepare("SELECT event_name, COUNT(*) as cnt FROM bets WHERE created_at >= date('now') GROUP BY event_name");
            $stmtCount->execute();
            $matchBetCounts = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR);

            // Fetch real balance from Betfair
            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            $activeBalance['available'] = (float) ($funds['availableToBetBalance'] ?? 0);
            $activeBalance['total'] = $activeBalance['available'] + abs((float) ($funds['exposure'] ?? 0));

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
                        // Pass the first market book for fallback extraction
                        $firstMarketId = $event['markets'][0]['marketId'] ?? null;
                        $firstBook = $booksMap[$firstMarketId] ?? null;
                        $event['api_football'] = $this->enrichWithApiData($event['event'], $event['sport'], $apiLiveFixtures, $event['competition'], $firstBook);
                    }

                    $gemini = new GeminiService();
                    $predictionRaw = $gemini->analyze([$event], [
                        'is_gianik' => true,
                        'available_balance' => $activeBalance['available'],
                        'current_portfolio' => $activeBalance['total']
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
                            if (!$selectedMarket || in_array($analysis['marketId'], $pendingMarketIds))
                                continue;

                            $stake = (float) ($analysis['stake'] ?? 2.0);
                            if ($stake < 2.0)
                                $stake = 2.0;

                            if ($activeBalance['available'] < $stake)
                                continue;

                            $runners = array_map(fn($r) => ['runnerName' => $r['name'], 'selectionId' => $r['selectionId']], $selectedMarket['runners']);
                            $selectionId = $this->bf->mapAdviceToSelection($analysis['advice'], $runners);

                            if ($selectionId) {
                                // Enforce minimum odds as per system rule
                                if (($analysis['odds'] ?? 0) < Config::MIN_BETFAIR_ODDS) {
                                    $analysis['odds'] = Config::MIN_BETFAIR_ODDS;
                                }

                                $betType = $globalMode;
                                $betfairId = null;

                                if ($betType === 'real') {
                                    $res = $this->bf->placeBet($analysis['marketId'], $selectionId, $analysis['odds'], $stake);
                                    if (($res['status'] ?? '') === 'SUCCESS') {
                                        $betfairId = $res['instructionReports'][0]['betId'] ?? null;
                                    } else {
                                        // If real bet fails, record as virtual for tracking? Or just skip?
                                        // For now, let's keep it as virtual so we see the fail in logs
                                        $betType = 'virtual';
                                    }
                                }

                                $motivation = $analysis['motivation'] ?? trim(preg_replace('/```json[\s\S]*?```/', '', $predictionRaw));
                                $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, type, betfair_id, motivation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmtInsert->execute([$analysis['marketId'], $selectedMarket['marketName'], $event['event'], $event['sport'], $selectionId, $analysis['advice'], $analysis['odds'], $stake, $betType, $betfairId, $motivation]);
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
            // Recupera tutte le scommesse pendenti (virtuali e reali)
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND created_at < datetime('now', '-5 minutes')");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pending))
                return;

            $marketIds = array_values(array_unique(array_column($pending, 'market_id')));
            $chunks = array_chunk($marketIds, 50);

            foreach ($chunks as $chunk) {
                $res = $this->bf->getMarketBooks($chunk);
                $marketBooks = $res['result'] ?? [];

                foreach ($marketBooks as $mb) {
                    if ($mb['status'] === 'CLOSED') {
                        $winnerSelectionId = null;
                        foreach ($mb['runners'] as $r) {
                            if (($r['status'] ?? '') === 'WINNER') {
                                $winnerSelectionId = $r['selectionId'];
                                break;
                            }
                        }

                        if ($winnerSelectionId !== null) {
                            // Troviamo tutte le scommesse locali per questo mercato
                            foreach ($pending as $bet) {
                                if ($bet['market_id'] === $mb['marketId']) {
                                    $isWin = ($winnerSelectionId == $bet['selection_id']);
                                    $status = $isWin ? 'won' : 'lost';
                                    $profit = $isWin ? ($bet['stake'] * ($bet['odds'] - 1)) : -$bet['stake'];

                                    $this->db->prepare("UPDATE bets SET status = ?, profit = ?, settled_at = CURRENT_TIMESTAMP WHERE id = ?")
                                        ->execute([$status, $profit, $bet['id']]);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("GiaNik Settlement Error: " . $e->getMessage());
        }
    }

    private function enrichWithApiData($bfEventName, $sport, $preFetchedLive = null, $competition = '', $bfMarketBook = null)
    {
        // Restricted to Soccer
        $countryCode = null;
        if ($bfMarketBook && isset($bfMarketBook['marketDefinition']['countryCode'])) {
            $countryCode = $bfMarketBook['marketDefinition']['countryCode'];
        }
        $apiMatch = $this->findMatchingFixture($bfEventName, $sport, $preFetchedLive, $countryCode);

        if (!$apiMatch) {
            // FALLBACK: If API-Football match not found, try to extract basic live data from Betfair
            if ($bfMarketBook && isset($bfMarketBook['marketDefinition'])) {
                $def = $bfMarketBook['marketDefinition'];
                $score = null;

                if (isset($def['score']['homeScore'], $def['score']['awayScore'])) {
                    $score = ['home' => (int) $def['score']['homeScore'], 'away' => (int) $def['score']['awayScore']];
                }

                if ($score || (isset($def['inPlay']) && $def['inPlay'])) {
                    return [
                        'live' => [
                            'live_score' => $score ?: ['home' => 0, 'away' => 0],
                            'live_status' => [
                                'short' => ($def['inPlay'] ?? false) ? 'LIVE' : 'NS',
                                'elapsed_minutes' => 0 // Betfair usually doesn't provide exact minute in API-NG easily
                            ],
                            'match_info' => ['fixture_id' => 'BF-' . ($bfMarketBook['marketId'] ?? 'unknown')]
                        ],
                        'note' => 'Dati estratti direttamente da Betfair (API-Football match non trovato).'
                    ];
                }
            }
            return null;
        }

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

    private function getCountryMapping($countryCode)
    {
        if (!$countryCode)
            return null;
        $map = [
            'GB' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'UK' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'PT' => 'Portugal',
            'CO' => 'Colombia',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'FR' => 'France',
            'DE' => 'Germany',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'TR' => 'Turkey',
            'GR' => 'Greece',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
            'PL' => 'Poland',
            'RO' => 'Romania',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'DK' => 'Denmark',
            'NO' => 'Norway',
            'SE' => 'Sweden',
            'HR' => 'Croatia',
            'CZ' => 'Czech Republic',
            'MX' => 'Mexico',
            'CL' => 'Chile',
            'PE' => 'Peru',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'EC' => 'Ecuador',
            'VE' => 'Venezuela',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'RS' => 'Serbia',
            'BG' => 'Bulgaria',
            'HU' => 'Hungary',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'CY' => 'Cyprus',
            'IL' => 'Israel',
            'US' => 'USA',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'CN' => 'China',
            'KR' => 'South Korea',
            'SA' => 'Saudi Arabia',
            'AE' => 'UAE',
            'QA' => 'Qatar',
            'IE' => 'Ireland',
            'PA' => 'Panama',
            'HN' => 'Honduras',
            'NI' => 'Nicaragua',
            'GT' => 'Guatemala',
            'SV' => 'El Salvador',
            'CR' => 'Costa Rica',
            'TH' => 'Thailand',
            'ID' => 'Indonesia',
            'MY' => 'Malaysia',
            'VN' => 'Vietnam',
            'EG' => 'Egypt',
            'MA' => 'Morocco',
            'TN' => 'Tunisia',
            'DZ' => 'Algeria',
            'ZA' => 'South Africa',
            'NG' => 'Nigeria',
            'GH' => 'Ghana',
            'CM' => 'Cameroon',
            'FI' => 'Finland',
            'IS' => 'Iceland',
            'AM' => 'Armenia'
        ];
        return $map[strtoupper($countryCode)] ?? null;
    }

    private function findMatchingFixture($bfEventName, $sport, $preFetchedLive = null, $countryCode = null)
    {
        $mappedCountry = $this->getCountryMapping($countryCode);
        $liveFixtures = $preFetchedLive;
        if (!$liveFixtures) {
            // Priority 1: Live matches (Centralized cache)
            $liveFixtures = $this->footballData->getLiveMatches()['response'] ?? [];

            // Priority 2: Broad date range (Yesterday to +2 days)
            $dates = [
                date('Y-m-d', strtotime('-1 day')),
                date('Y-m-d'),
                date('Y-m-d', strtotime('+1 day')),
                date('Y-m-d', strtotime('+2 days')),
            ];

            foreach ($dates as $date) {
                $dateRes = $this->footballData->getFixturesByDate($date)['response'] ?? [];
                $liveFixtures = array_merge($liveFixtures, $dateRes);
            }
        }
        return $this->footballData->searchInFixtureList($bfEventName, $liveFixtures, $mappedCountry);
    }

    public function recentBets()
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        try {
            // Sincronizza prima di mostrare la sidebar
            $this->syncWithBetfair();

            if (isset($_GET['status']))
                $_SESSION['recent_bets_status'] = $_GET['status'];

            $statusFilter = $_SESSION['recent_bets_status'] ?? 'all';

            // 1. Sport mapping
            $sportMapping = [
                'Soccer' => 'Calcio',
                'Football' => 'Calcio',
                'Tennis' => 'Tennis',
                'Basketball' => 'Basket'
            ];

            // 2. Main query for bets
            $sql = "SELECT * FROM bets";
            $where = [];
            $params = [];

            if ($statusFilter === 'won') {
                $where[] = "status = 'won'";
            } elseif ($statusFilter === 'lost') {
                $where[] = "status = 'lost'";
            } elseif ($statusFilter === 'pending') {
                $where[] = "status = 'pending'";
            } else {
                // Default: exclude technical cancellations
                $where[] = "status NOT IN ('cancelled', 'voided')";
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " GROUP BY market_id, selection_id, odds, stake, type, status";
            $sql .= " ORDER BY created_at DESC LIMIT 30";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Map sport names for bets
            foreach ($bets as &$bet) {
                $bet['sport_it'] = $sportMapping[$bet['sport']] ?? $bet['sport'];
            }

            $currentStatus = $statusFilter;
            $currentType = 'all'; // Legacy support for view variables

            require __DIR__ . '/../Views/partials/recent_bets_sidebar.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-2 text-[10px]">' . $e->getMessage() . '</div>';
        }
    }

    private function getVirtualBalance()
    {
        $vInit = 100.0;
        // Sum ALL virtual bets profit
        $vProf = (float) $this->db->query("SELECT SUM(profit) FROM bets WHERE type = 'virtual' AND status IN ('won', 'lost')")->fetchColumn();
        // Sum ALL virtual bets exposure
        $vExp = (float) $this->db->query("SELECT SUM(stake) FROM bets WHERE type = 'virtual' AND status = 'pending'")->fetchColumn();
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
            $detailsRaw = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $detailsRaw['status_short'] ?? 'NS';
            $predictionsData = $this->footballData->getFixturePredictions($fixtureId, $statusShort);

            if (!$predictionsData) {
                echo '<div class="p-10 text-center text-danger font-black uppercase italic">Pronostico non disponibile.</div>';
                return;
            }

            // Extract real sub-objects from the stored full response
            $raw = $predictionsData['prediction_json'];
            $prediction = $raw['predictions'] ?? $raw;
            if (isset($prediction['predictions'])) {
                $prediction = $prediction['predictions'];
            }
            $comparison = $predictionsData['comparison_json'] ?? $raw['comparison'] ?? [];

            // Map flat fixture details to the structure expected by the view
            $details = [
                'teams' => [
                    'home' => ['name' => $detailsRaw['team_home_name'] ?? 'Home'],
                    'away' => ['name' => $detailsRaw['team_away_name'] ?? 'Away']
                ]
            ];

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

            // Try to get predictions - if not in DB, sync it
            $predictionsData = $this->footballData->getFixturePredictions($fixtureId, $statusShort);
            $predictions = null;
            if ($predictionsData && isset($predictionsData['prediction_json'])) {
                $raw = $predictionsData['prediction_json'];
                $predictions = $raw['predictions'] ?? $raw;
                if (isset($predictions['predictions'])) {
                    $predictions = $predictions['predictions'];
                }
            }

            require __DIR__ . '/../Views/partials/match_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger text-[10px]">Error loading details: ' . $e->getMessage() . '</div>';
        }
    }

    public function matchStats($fixtureId)
    {
        try {
            $detailsRaw = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $detailsRaw['status_short'] ?? 'NS';
            $stats = $this->footballData->getFixtureStatistics($fixtureId, $statusShort);

            $details = [
                'fixture' => ['id' => $detailsRaw['id']],
                'teams' => [
                    'home' => ['id' => $detailsRaw['team_home_id'], 'name' => $detailsRaw['team_home_name'], 'logo' => $detailsRaw['team_home_logo']],
                    'away' => ['id' => $detailsRaw['team_away_id'], 'name' => $detailsRaw['team_away_name'], 'logo' => $detailsRaw['team_away_logo']]
                ]
            ];

            require __DIR__ . '/../Views/partials/modals/stats.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function matchLineups($fixtureId)
    {
        try {
            $detailsRaw = $this->footballData->getFixtureDetails($fixtureId);
            $statusShort = $detailsRaw['status_short'] ?? 'NS';
            $lineups = $this->footballData->getFixtureLineups($fixtureId, $statusShort);

            $details = [
                'fixture' => ['id' => $detailsRaw['id']],
                'teams' => [
                    'home' => ['id' => $detailsRaw['team_home_id'], 'name' => $detailsRaw['team_home_name'], 'logo' => $detailsRaw['team_home_logo']],
                    'away' => ['id' => $detailsRaw['team_away_id'], 'name' => $detailsRaw['team_away_name'], 'logo' => $detailsRaw['team_away_logo']]
                ]
            ];

            require __DIR__ . '/../Views/partials/modals/lineups.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function matchH2H($fixtureId)
    {
        try {
            $detailsRaw = $this->footballData->getFixtureDetails($fixtureId);
            // We need teams IDs to get H2H
            $homeId = $detailsRaw['team_home_id'];
            $awayId = $detailsRaw['team_away_id'];
            $h2hData = $this->footballData->getH2H($homeId, $awayId);
            $h2h = $h2hData['h2h_json'] ?? [];

            $details = [
                'teams' => [
                    'home' => ['name' => $detailsRaw['team_home_name'], 'logo' => $detailsRaw['team_home_logo']],
                    'away' => ['name' => $detailsRaw['team_away_name'], 'logo' => $detailsRaw['team_away_logo']]
                ]
            ];

            require __DIR__ . '/../Views/partials/modals/h2h.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function teamDetails($teamId)
    {
        try {
            $leagueId = $_GET['leagueId'] ?? null;
            $season = $_GET['season'] ?? \App\Config\Config::getCurrentSeason();
            $fixtureId = $_GET['fixtureId'] ?? null;

            $teamData = $this->footballData->getTeamDetails($teamId);
            if (!$teamData) {
                echo '<div class="p-10 text-center text-danger">Team non trovato.</div>';
                return;
            }

            $venue = (new \App\Models\Venue())->getById($teamData['venue_id'] ?? 0);
            $coachData = $this->footballData->getCoach($teamId);
            $squad = $this->footballData->getSquad($teamId);

            $standings = [];
            if ($leagueId) {
                $standings = $this->footballData->getStandings($leagueId, $season, $teamId);
            } else {
                // Try to find if we have any standings for this team in current season
                $db = \App\Services\Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT league_id FROM standings WHERE team_id = ? AND season = ? LIMIT 1");
                $stmt->execute([$teamId, $season]);
                $foundLeague = $stmt->fetchColumn();
                if ($foundLeague) {
                    $standings = $this->footballData->getStandings($foundLeague, $season, $teamId);
                }
            }

            $team = [
                'team' => $teamData,
                'venue' => $venue ?: $teamData, // Fallback if venue not in separate table
                'coach' => $coachData,
                'squad' => $squad,
                'standing' => $standings[0] ?? null,
                'fixtureId' => $fixtureId
            ];

            require __DIR__ . '/../Views/partials/modals/team_details.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }

    public function playerDetails($playerId, $fixtureId = null)
    {
        try {
            $fixtureId = $fixtureId ?: ($_GET['fixtureId'] ?? null);
            $season = $_GET['season'] ?? \App\Config\Config::getCurrentSeason();

            // 1. Get Base Player Data + Seasonal Stats
            $playerData = $this->footballData->getPlayer($playerId, $season);

            if (!$playerData) {
                echo '<div class="p-10 text-center text-danger">Giocatore non trovato.</div>';
                return;
            }

            // 2. Extra data 
            $playerModel = new \App\Models\Player();
            $career = $playerModel->getCareer($playerId);
            $transfers = $playerModel->getTransfers($playerId);
            $trophies = $playerModel->getTrophies($playerId);
            $sidelined = $playerModel->getSidelined($playerId);

            // Fetch season stats if not already in $playerData
            $statsRaw = (new \App\Models\PlayerStatistics())->get($playerId, $season);
            $statistics = ($statsRaw && isset($statsRaw['stats_json'])) ? json_decode($statsRaw['stats_json'], true) : [];

            $player = [
                'player' => $playerData,
                'statistics' => $statistics,
                'career' => $career,
                'transfers' => $transfers,
                'trophies' => $trophies,
                'sidelined' => $sidelined
            ];

            // 3. Live Data (Live match cross-check)
            if ($fixtureId) {
                $detailsRaw = $this->footballData->getFixtureDetails($fixtureId);
                if ($detailsRaw) {
                    $statusShort = $detailsRaw['status_short'] ?? 'NS';
                    $playersStats = $this->footballData->getFixturePlayerStatistics($fixtureId, $statusShort);
                    $events = $this->footballData->getFixtureEvents($fixtureId, $statusShort);

                    // Find match-specific stats
                    foreach ($playersStats as $row) {
                        if ($row['player_id'] == $playerId) {
                            $matchStats = $row['stats_json'];
                            $player['team'] = ['id' => $row['team_id'], 'name' => $row['team_name'], 'logo' => $row['team_logo']];

                            // Merge/Override with match stats for the current view
                            if (!empty($matchStats['statistics'])) {
                                array_unshift($player['statistics'], $matchStats['statistics'][0]);
                            }
                            break;
                        }
                    }

                    // Cross-check with events
                    $eventGoals = 0;
                    $yellowCards = 0;
                    $redCards = 0;
                    foreach ($events as $ev) {
                        if (($ev['player']['id'] ?? null) == $playerId) {
                            $type = strtolower($ev['type'] ?? '');
                            if ($type === 'goal')
                                $eventGoals++;
                            if ($type === 'card') {
                                $detail = strtolower($ev['detail'] ?? '');
                                if (strpos($detail, 'yellow') !== false)
                                    $yellowCards++;
                                if (strpos($detail, 'red') !== false)
                                    $redCards++;
                            }
                        }
                    }

                    if (isset($player['statistics'][0])) {
                        $stats = &$player['statistics'][0];
                        if (($stats['goals']['total'] ?? 0) < $eventGoals)
                            $stats['goals']['total'] = $eventGoals;
                        if (($stats['cards']['yellow'] ?? 0) < $yellowCards)
                            $stats['cards']['yellow'] = $yellowCards;
                        if (($stats['cards']['red'] ?? 0) < $redCards)
                            $stats['cards']['red'] = $redCards;

                        if (($eventGoals > 0 || $yellowCards > 0 || $redCards > 0) && ($stats['games']['minutes'] ?? 0) === 0) {
                            $stats['games']['minutes'] = 'Live';
                        }
                    }
                }
            }

            // Ensure we have at least one statistics block
            if (empty($player['statistics'])) {
                $player['statistics'] = [
                    [
                        'team' => ['id' => null, 'name' => 'N/D'],
                        'league' => ['id' => null, 'name' => 'N/D'],
                        'games' => ['minutes' => 0, 'position' => 'N/A', 'rating' => null],
                        'goals' => ['total' => 0, 'assists' => 0],
                        'cards' => ['yellow' => 0, 'red' => 0]
                    ]
                ];
            }

            require __DIR__ . '/../Views/partials/modals/player_stats.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-4">Errore: ' . $e->getMessage() . '</div>';
        }
    }
    public function syncWithBetfair()
    {
        try {
            // 1. Pulizia orfani immediati (record reali creati ma non ancora associati a un ID Betfair, scaduti)
            $this->db->exec("DELETE FROM bets WHERE type = 'real' AND betfair_id IS NULL AND created_at < datetime('now', '-2 minutes')");

            // 2. Recupera ordini da Betfair (Settled e Current)
            $clearedRes = $this->bf->getClearedOrders();
            $clearedOrders = $clearedRes['clearedOrders'] ?? [];

            $currentRes = $this->bf->getCurrentOrders();
            $currentOrders = $currentRes['currentOrders'] ?? [];

            $allBfOrders = [];
            $marketIdsToFetch = [];
            $eventIdsToFetch = [];

            // Elabora scommesse APERTE
            foreach ($currentOrders as $o) {
                $allBfOrders[$o['betId']] = [
                    'id' => $o['betId'],
                    'marketId' => $o['marketId'],
                    'eventId' => null,
                    'selectionId' => $o['selectionId'],
                    'odds' => $o['priceSize']['price'] ?? 0,
                    'stake' => $o['priceSize']['size'] ?? 0,
                    'status' => 'pending',
                    'profit' => 0,
                    'side' => $o['side'] ?? 'BACK',
                    'placedDate' => $o['placedDate'] ?? null,
                    'marketName' => null,
                    'runnerName' => null
                ];
                // listCurrentOrders non dà descrizioni, quindi dobbiamo recuperarle dal catalogo
                $marketIdsToFetch[] = $o['marketId'];
            }

            // Elabora scommesse CHIUSE
            foreach ($clearedOrders as $o) {
                $itemDesc = $o['itemDescription'] ?? [];
                $allBfOrders[$o['betId']] = [
                    'id' => $o['betId'],
                    'marketId' => $o['marketId'],
                    'eventId' => $o['eventId'] ?? null,
                    'selectionId' => $o['selectionId'],
                    'odds' => $o['priceRequested'] ?? 0,
                    'stake' => $o['sizeSettled'] ?? 0,
                    'status' => ($o['betOutcome'] === 'WIN' || $o['betOutcome'] === 'WON') ? 'won' : (($o['betOutcome'] === 'VOIDED' || $o['betOutcome'] === 'CANCELLED') ? 'cancelled' : 'lost'),
                    'profit' => (float) ($o['profit'] ?? 0),
                    'side' => $o['side'] ?? 'BACK',
                    'placedDate' => $o['placedDate'] ?? null,
                    'marketName' => $itemDesc['marketDesc'] ?? ($itemDesc['marketName'] ?? null),
                    'runnerName' => $itemDesc['runnerDesc'] ?? ($itemDesc['runnerName'] ?? null),
                    'eventName' => $itemDesc['eventDesc'] ?? null
                ];

                if (isset($o['eventId']))
                    $eventIdsToFetch[] = $o['eventId'];

                // Se non abbiamo nomi per mercati chiusi, aggiungiamo ai marketId da recuperare come fallback
                if (empty($allBfOrders[$o['betId']]['marketName'])) {
                    $marketIdsToFetch[] = $o['marketId'];
                }
            }

            // 3. Recupera Info Mancanti (Nomi Eventi e Cataloghi Mercati)
            $eventNameMap = [];
            $marketInfoMap = [];

            // 3a. Recupero Nomi Eventi tramite listEvents
            $eventIdsToFetch = array_values(array_unique(array_filter($eventIdsToFetch)));
            if (!empty($eventIdsToFetch)) {
                $chunks = array_chunk($eventIdsToFetch, 50);
                foreach ($chunks as $chunk) {
                    $evRes = $this->bf->request('listEvents', ['filter' => ['eventIds' => $chunk]]);
                    foreach ($evRes['result'] ?? [] as $ev) {
                        $eventNameMap[$ev['event']['id']] = $ev['event']['name'];
                    }
                }
            }

            // 3b. Recupero Cataloghi Mercati tramite listMarketCatalogue (Solo se necessari)
            $marketIdsToFetch = array_values(array_unique(array_filter($marketIdsToFetch)));
            if (!empty($marketIdsToFetch)) {
                $chunks = array_chunk($marketIdsToFetch, 50);
                foreach ($chunks as $chunk) {
                    $catRes = $this->bf->request('listMarketCatalogue', [
                        'filter' => [
                            'marketIds' => $chunk,
                            'marketStatus' => ['OPEN', 'CLOSED', 'INACTIVE', 'COMPLETED']
                        ],
                        'maxResults' => 1000,
                        'marketProjection' => ['EVENT', 'MARKET_DESCRIPTION', 'RUNNER_DESCRIPTION']
                    ]);
                    foreach ($catRes['result'] ?? [] as $cat) {
                        $runners = [];
                        foreach ($cat['runners'] as $r) {
                            $runners[$r['selectionId']] = $r['runnerName'];
                        }
                        $marketInfoMap[$cat['marketId']] = [
                            'event' => $cat['event']['name'] ?? null,
                            'market' => $cat['marketName'] ?? null,
                            'runners' => $runners
                        ];
                    }
                }
            }

            // 4. MIRRORING & UPDATE: Sincronizza lo stato locale con Betfair
            foreach ($allBfOrders as $betId => $o) {
                $info = $marketInfoMap[$o['marketId']] ?? null;

                // Priorità Nome Evento: 1. Catalogo, 2. ItemDesc (eventDesc), 3. EventMap
                $eventName = $info['event'] ?? ($o['eventName'] ?? ($eventNameMap[$o['eventId'] ?? ''] ?? 'Unknown Event'));
                $marketName = $info['market'] ?? ($o['marketName'] ?? 'Unknown Market');
                $runnerName = $info['runners'][$o['selectionId']] ?? ($o['runnerName'] ?? 'Selection ' . $o['selectionId']);

                $placedDate = isset($o['placedDate']) ? date('Y-m-d H:i:s', strtotime($o['placedDate'])) : date('Y-m-d H:i:s');

                // Verifica esistenza nel DB locale
                $stmt = $this->db->prepare("SELECT id FROM bets WHERE betfair_id = ?");
                $stmt->execute([$betId]);
                $dbId = $stmt->fetchColumn();

                if ($dbId) {
                    // Update: aggiorna sempre per avere l'ultimo stato (profitto, status)
                    $stmtUpdate = $this->db->prepare("UPDATE bets SET status = ?, profit = ?, market_id = ?, market_name = ?, event_name = ?, runner_name = ?, created_at = ? WHERE id = ?");
                    $stmtUpdate->execute([$o['status'], $o['profit'], $o['marketId'], $marketName, $eventName, $runnerName, $placedDate, $dbId]);
                } else {
                    // Import Automatico (se non esisteva)
                    $stmtInsert = $this->db->prepare("INSERT INTO bets 
                        (betfair_id, market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, status, type, profit, created_at) 
                        VALUES (?, ?, ?, ?, 'Soccer', ?, ?, ?, ?, ?, 'real', ?, ?)");
                    $stmtInsert->execute([
                        $betId,
                        $o['marketId'],
                        $marketName,
                        $eventName,
                        $o['selectionId'],
                        $runnerName,
                        $o['odds'],
                        $o['stake'],
                        $o['status'],
                        $o['profit'],
                        $placedDate
                    ]);
                }
            }

            // 5. MIRRORING TOTALE: Rimuovi record locali REAL non presenti su Betfair
            // Pulizia Preventiva Duplicati locali per BetID (salvaguardia contro record orfani duplicati)
            $this->db->exec("DELETE FROM bets WHERE id NOT IN (SELECT MIN(id) FROM bets WHERE betfair_id IS NOT NULL GROUP BY betfair_id) AND betfair_id IS NOT NULL AND type = 'real'");

            $bfIdsList = array_keys($allBfOrders);
            // Verifica se abbiamo avuto risposte valide (anche se vuote) da Betfair per procedere alla pulizia
            $isValidResponse = isset($clearedRes['clearedOrders']) || isset($currentRes['currentOrders']);

            if ($isValidResponse) {
                $sqlDelete = "DELETE FROM bets WHERE type = 'real' AND created_at < datetime('now', '-2 minutes')";
                if (!empty($bfIdsList)) {
                    $placeholders = implode(',', array_fill(0, count($bfIdsList), '?'));
                    $sqlDelete .= " AND betfair_id NOT IN ($placeholders)";
                    $stmtDelete = $this->db->prepare($sqlDelete);
                    $stmtDelete->execute($bfIdsList);
                } else {
                    // Se Betfair conferma che non ci sono ordini, svuotiamo la lista 'real' locale (vecchia)
                    $this->db->exec($sqlDelete);
                }

                // Pulizia record manuali senza ID Betfair (residui orfani oramai vecchi)
                $this->db->exec("DELETE FROM bets WHERE type = 'real' AND betfair_id IS NULL AND created_at < datetime('now', '-2 minutes')");
            }

        } catch (\Throwable $e) {
            file_put_contents(\App\Config\Config::LOGS_PATH . 'gianik_sync_error.log', date('[Y-m-d H:i:s] ') . "Sync Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}
