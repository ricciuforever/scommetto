<?php
// app/GiaNik/Controllers/GiaNikController.php

namespace App\GiaNik\Controllers;

use App\Config\Config;
use App\Services\BetfairService;
use App\Services\GeminiService;
use App\Services\FootballApiService;
use App\Services\FootballDataService;
use App\Services\BasketballApiService;
use App\Services\IntelligenceService;
use App\Services\MoneyManagementService;
use App\GiaNik\GiaNikDatabase;
use PDO;

class GiaNikController
{
    private $bf;
    private $db;
    private $footballData;
    private $intelligence;

    public function __construct()
    {
        set_time_limit(600);
        $this->bf = new BetfairService();
        $this->db = GiaNikDatabase::getInstance()->getConnection();
        $this->footballData = new FootballDataService();
        $this->intelligence = new IntelligenceService();

        // Ensure match_mappings table exists
        $this->db->exec("CREATE TABLE IF NOT EXISTS match_mappings (
            betfair_event_id TEXT PRIMARY KEY,
            fixture_id INTEGER NOT NULL,
            mapped_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_match_mappings_fixture_id ON match_mappings(fixture_id)");
    }

    public function index()
    {
        require __DIR__ . '/../Views/gianik_live_page.php';
    }

    public function brain()
    {
        require_once __DIR__ . '/BrainController.php';
        $controller = new BrainController();
        $controller->index();
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

            // --- Active Bets Tracking (Real & Virtual) ---
            $stmtBets = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending'");
            $stmtBets->execute();
            $allActiveBets = $stmtBets->fetchAll(PDO::FETCH_ASSOC);
            $activeMarketIds = array_column($allActiveBets, 'market_id');

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

            // --- Performance Optimization: Detect if there are live matches ---
            $anyLive = false;
            foreach ($marketBooksMap as $mb) {
                if ($mb['marketDefinition']['inPlay'] ?? false) {
                    $anyLive = true;
                    break;
                }
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
            $processedCount = 0;
            foreach ($marketCatalogues as $mc) {
                $marketId = $mc['marketId'];
                $eventId = $mc['event']['id'];

                if (isset($processedEvents[$eventId]))
                    continue;
                if (!isset($marketBooksMap[$marketId]))
                    continue;

                $mb = $marketBooksMap[$marketId];
                $isInPlay = $mb['marketDefinition']['inPlay'] ?? false;
                $hasActiveBet = in_array($marketId, $activeMarketIds);

                // --- EARLY FILTERING TO PREVENT 504 TIMEOUT ---
                // Se ci sono match live, processiamo SOLO i match live o quelli con scommesse attive
                if ($anyLive && !$isInPlay && !$hasActiveBet) {
                    continue;
                }

                // Se NON ci sono match live, limitiamo comunque il numero di match processati (max 40)
                // per evitare di saturare il gateway con centinaia di match futuri.
                if (!$anyLive && $processedCount >= 40 && !$hasActiveBet) {
                    continue;
                }

                $processedEvents[$eventId] = true;
                $processedCount++;
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

                $countryCode = $mc['event']['countryCode'] ?? null;
                $mappedName = $this->getCountryMapping($countryCode);
                $displayCountry = is_array($mappedName) ? $mappedName[0] : ($mappedName ?: ($countryCode ?: 'World'));
                $fallbackFlag = $countryCode ? "https://media.api-sports.io/flags/" . strtolower($countryCode) . ".svg" : "https://media.api-sports.io/flags/world.svg";

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
                    'country' => $displayCountry,
                    'flag' => $fallbackFlag,
                    'is_in_play' => $mb['marketDefinition']['inPlay'] ?? false
                ];

                // --- Enrichment ---
                $foundApiData = false;
                if ($sport === 'Soccer') {
                    $mappedCountry = $mappedName;

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

                    $match = $this->findMatchingFixture($m['event'], $sport, $apiLiveMatches, $countryCode, $startTime, $m['event_id']);

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
                        if ($statusShort !== 'NS') {
                            $m['status_label'] = $statusShort . ($elapsed ? " $elapsed'" : "");
                        }
                        $m['elapsed'] = $elapsed;
                        $m['status_short'] = $statusShort;
                        $m['has_api_data'] = true;
                        $m['intensity'] = $this->getLastIntensityBadge($fid);
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
                    $m['has_active_virtual_bet'] = false;
                    $m['current_pl'] = 0;
                    $m['just_updated'] = false;
                    $m['my_bets'] = [];

                    // Track active bets for this match
                    foreach ($allActiveBets as $bet) {
                        if ($bet['market_id'] === $m['marketId']) {
                            // Filter for matched bets only if real
                            if ($bet['type'] === 'real' && ($bet['size_matched'] ?? 0) <= 0) {
                                continue;
                            }

                            if ($bet['type'] === 'real') $m['has_active_real_bet'] = true;
                            if ($bet['type'] === 'virtual') $m['has_active_virtual_bet'] = true;

                            // Find current Back odds for this runner
                            $currentOdds = 0;
                            foreach ($m['runners'] as $r) {
                                if ($r['selectionId'] == $bet['selection_id']) {
                                    $currentOdds = is_numeric($r['back']) ? (float) $r['back'] : 0;
                                    break;
                                }
                            }

                            $pl = 0;
                            $effectiveStake = ($bet['type'] === 'real') ? ($bet['size_matched'] ?? 0) : $bet['stake'];
                            if ($currentOdds > 1.0 && $effectiveStake > 0) {
                                // Estimated Cashout: (Placed Odds / Current Odds) * Stake - Stake
                                $pl = (($bet['odds'] / $currentOdds) * $effectiveStake) - $effectiveStake;
                            }

                            $m['my_bets'][] = [
                                'type' => $bet['type'],
                                'runner' => $bet['runner_name'],
                                'odds' => $bet['odds'],
                                'stake' => $bet['type'] === 'real' ? $bet['size_matched'] : $bet['stake'],
                                'pl' => $pl
                            ];

                            if ($bet['type'] === 'real') {
                                $m['current_pl'] += $pl;
                            }
                        }
                    }

                    // Detect Score Changes
                    $scoreKey = $m['event_id'];
                    $currentScore = $m['score'] ?? '0-0';
                    $newScores[$scoreKey] = $currentScore;
                    if (isset($prevScores[$scoreKey]) && $prevScores[$scoreKey] !== $currentScore) {
                        $m['just_updated'] = time();
                        $m['is_goal'] = true;
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


            // Funds
            $account = ['available' => 0, 'exposure' => 0];
            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            if (isset($funds['availableToBetBalance'])) {
                $account['available'] = $funds['availableToBetBalance'] ?? 0;
                $account['exposure'] = abs($funds['exposure'] ?? 0);
            }

            // Global State for GiaNik
            $stmtMode = $this->db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
            $stmtMode->execute();
            $operationalMode = $stmtMode->fetchColumn() ?: 'real';

            // Calculate Portfolio Stats
            $realPortfolioStats = $this->getPortfolioStats('real', $account['available'] + $account['exposure']);
            $virtualPortfolioStats = $this->getPortfolioStats('virtual');

            // Default portfolioStats for the main chart follows operational mode
            $portfolioStats = ($operationalMode === 'real') ? $realPortfolioStats : $virtualPortfolioStats;

            $virtualAccount = $this->getVirtualBalance();
            $settlementResults = $this->settleBets();
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
                'markets' => [],
                'active_bets' => []
            ];

            // Recupera eventuali scommesse già attive su questo evento
            $stmtActive = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND event_name = ?");
            $stmtActive->execute([$eventName]);
            $event['active_bets'] = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

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

            // Fetch operational mode
            $stmtMode = $this->db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
            $stmtMode->execute();
            $operationalMode = $stmtMode->fetchColumn() ?: 'real';

            if ($operationalMode === 'real') {
                $fundsData = $this->bf->getFunds();
                $funds = $fundsData['result'] ?? $fundsData;
                $total = (float) ($funds['availableToBetBalance'] ?? 0) + abs((float) ($funds['exposure'] ?? 0));
                $balance = [
                    'available_balance' => (float) ($funds['availableToBetBalance'] ?? 0),
                    'current_portfolio' => $total,
                    'net_pnl' => round($total - Config::DEFAULT_INITIAL_BANKROLL, 2)
                ];
            } else {
                $vBalance = $this->getVirtualBalance();
                $balance = [
                    'available_balance' => $vBalance['available'],
                    'current_portfolio' => $vBalance['total'],
                    'net_pnl' => round($vBalance['total'] - Config::DEFAULT_INITIAL_BANKROLL, 2)
                ];
            }

            // Aggiungiamo il marketId di partenza come suggerimento per l'AI
            $event['requestedMarketId'] = $marketId;

            if ($event['sport'] === 'Soccer' || $event['sport'] === 'Football') {
                // Pass the initial market book for fallback extraction
                $mainBook = $booksMap[$marketId] ?? null;
                $apiData = $this->enrichWithApiData($event['event'], $event['sport'], null, $event['competition'], $mainBook);
                $event['api_football'] = $apiData;

                // --- Deep Context Integration ---
                if (!empty($apiData['fixture'])) {
                    $fix = $apiData['fixture'];
                    $deepCtx = $this->intelligence->getDeepContext($fix['id'], $fix['team_home_id'], $fix['team_away_id'], $fix['league_id']);
                    $event['deep_context'] = $this->summarizeDeepContext($deepCtx);
                }

                // --- Performance Metrics Integration ---
                $marketType = $this->intelligence->parseMarketType($initialMc['marketName'] ?? '');
                $teams = $this->intelligence->parseTeams($event['event']);
                $event['performance_metrics'] = $this->intelligence->getPerformanceContext(
                    $teams['home'] ?? '',
                    $teams['away'] ?? '',
                    $marketType,
                    $event['competition'],
                    $apiData['fixture']['league_id'] ?? null
                );

                // --- GATEKEEPER CHECK ---
                // Verifica performance della Lega
                $leagueId = $apiData['fixture']['league_id'] ?? 0;
                $blockReason = $this->checkGatekeeper($leagueId);
                if ($blockReason) {
                    $reasoning = "⛔ " . $blockReason;
                    require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
                    return;
                }

                // --- Circuit Breaker (Recent Trauma) ---
                if (!empty($apiData['events'])) {
                    $currentMin = (int)($apiData['live']['live_status']['elapsed_minutes'] ?? 0);
                    foreach ($apiData['events'] as $ev) {
                        $eventMin = (int)($ev['time']['elapsed'] ?? 0);
                        $type = $ev['type'] ?? '';
                        $detail = strtolower($ev['detail'] ?? '');

                        $isCritical = false;
                        if ($type === 'Goal') $isCritical = true;
                        if ($type === 'Card' && strpos($detail, 'red') !== false) $isCritical = true;

                        if (($currentMin - $eventMin) <= 4 && $isCritical) {
                            $reasoning = "MERCATO INSTABILE: Evento critico ($type " . ($type === 'Card' ? 'ROSSO' : '') . ") avvenuto al minuto $eventMin (meno di 4 minuti fa). Analisi sospesa per assestamento mercato.";
                            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
                            return;
                        }
                    }
                }

                // --- AI Lessons (Post-Mortem) ---
                $event['ai_lessons'] = $this->getRecentLessons($apiData);
            }

            $gemini = new GeminiService();

            // Fetch Dynamic Config
            $strategyPrompt = $this->db->query("SELECT value FROM system_state WHERE key = 'strategy_prompt'")->fetchColumn();
            $stakeMode = $this->db->query("SELECT value FROM system_state WHERE key = 'stake_mode'")->fetchColumn() ?: 'kelly';
            $stakeValue = (float)($this->db->query("SELECT value FROM system_state WHERE key = 'stake_value'")->fetchColumn() ?: 0.15);
            $minStake = (float)($this->db->query("SELECT value FROM system_state WHERE key = 'min_stake'")->fetchColumn() ?: 2.00);

            // Debug $event
            file_put_contents(Config::LOGS_PATH . 'gianik_event_debug.log', date('[Y-m-d H:i:s] ') . json_encode($event, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

            $predictionRaw = $gemini->analyze([$event], array_merge($balance, [
                'is_gianik' => true,
                'custom_prompt' => $strategyPrompt
            ]));

            $analysis = json_decode($predictionRaw, true);
            $jsonContent = $predictionRaw;

            // Fallback for markdown format or truncated JSON
            if ($analysis === null || !is_array($analysis)) {
                if (preg_match('/```json\s*([\s\S]*?)(?:```|$)/', $predictionRaw, $matches)) {
                    $jsonContent = trim($matches[1]);
                    $analysis = json_decode($jsonContent, true);
                }

                // If still null, attempt to fix truncated JSON (e.g. missing closing braces)
                if ($analysis === null && !empty($jsonContent)) {
                    $bracesOpen = substr_count($jsonContent, '{');
                    $bracesClose = substr_count($jsonContent, '}');
                    if ($bracesOpen > $bracesClose) {
                        $fixedJson = $jsonContent . str_repeat('}', $bracesOpen - $bracesClose);
                        $analysis = json_decode($fixedJson, true);
                    }
                }
            }

            // Calcolo Stake con MoneyManager se l'analisi è valida
            $decision = ['stake' => 0, 'reason' => 'N/A', 'is_value_bet' => false];
            if ($analysis && isset($analysis['confidence'], $analysis['odds'])) {
                $decision = MoneyManagementService::calculateStake(
                    $balance['current_portfolio'],
                    (float)$analysis['odds'],
                    (float)$analysis['confidence'],
                    $stakeMode,
                    $stakeValue,
                    $minStake
                );
                // Aggiorniamo lo stake suggerito nell'analisi per la vista
                $analysis['stake'] = $decision['stake'];
            }

            // --- AI Output Validation ---
            if (!empty($analysis['marketId']) && $analysis['marketId'] !== ($event['requestedMarketId'] ?? '')) {
                $reasoning = "ERRORE VALIDAZIONE: L'AI ha suggerito un'operazione su un mercato diverso ({$analysis['marketId']}) da quello richiesto. Analisi scartata per sicurezza.";
            } else {
                $reasoning = $this->extractMotivation($analysis, $predictionRaw, $event);
            }

            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
        } catch (\Throwable $e) {
            $reasoning = "Si è verificato un errore durante l'analisi: " . $e->getMessage();
            require __DIR__ . '/../Views/partials/modals/gianik_analysis.php';
        }
    }

    private function sendJsonHeader()
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    public function placeBet()
    {
        $this->sendJsonHeader();
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

            // --- Robust Check for Pending Bets on Same Match ---
            $fixtureId = $input['fixtureId'] ?? null;
            if ($this->isBetAlreadyPendingForMatch($fixtureId, $eventName)) {
                echo json_encode(['status' => 'error', 'message' => 'Esiste già una scommessa aperta per questo match.']);
                return;
            }

            // Enforce minimum odds as per system rule
            if ($odds < Config::MIN_BETFAIR_ODDS) {
                $odds = Config::MIN_BETFAIR_ODDS;
            }

            // --- Staking Guardrail: Max 5% of Total Balance ---
            $minStake = (float)($this->db->query("SELECT value FROM system_state WHERE key = 'min_stake'")->fetchColumn() ?: 2.00);
            $effectiveMinStake = max(2.00, $minStake);

            if ($type === 'real') {
                $fundsData = $this->bf->getFunds();
                $funds = $fundsData['result'] ?? $fundsData;
                $totalBal = (float)($funds['availableToBetBalance'] ?? 0) + abs((float)($funds['exposure'] ?? 0));
                $availableBal = (float)($funds['availableToBetBalance'] ?? 0);
            } else {
                $vBal = $this->getVirtualBalance();
                $totalBal = $vBal['total'];
                $availableBal = $vBal['available'];
            }

            $maxAllowed = $totalBal * 0.05;
            if ($stake > $maxAllowed) {
                $stake = $maxAllowed;
            }
            if ($stake > $availableBal) {
                $stake = $availableBal;
            }

            // Enforce minimum IT stake
            if ($stake < $effectiveMinStake) {
                $stake = $effectiveMinStake;
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

            $bucket = $this->getOddsBucket($odds);
            $leagueName = $input['competition'] ?? 'Unknown';
            $leagueId = $input['leagueId'] ?? null;

            // Determine current period for manual bets
            $pMinute = 0;
            $pPeriod = 'NS';
            if ($sport === 'Soccer' || $sport === 'Football') {
                $liveRes = $this->footballData->getLiveMatches();
                $matchingFixture = $this->footballData->searchInFixtureList($eventName, $liveRes['response'] ?? []);
                if ($matchingFixture) {
                    $status = $matchingFixture['fixture']['status']['short'] ?? 'NS';
                    $pMinute = (int) ($matchingFixture['fixture']['status']['elapsed'] ?? 0);
                    if (in_array($status, ['1H', 'HT']))
                        $pPeriod = '1H';
                    elseif (in_array($status, ['2H', 'ET', 'BT', 'P']))
                        $pPeriod = '2H';
                }
            }

            $stmt = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, type, betfair_id, motivation, bucket, league, league_id, fixture_id, placed_at_minute, placed_at_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$marketId, $marketName, $eventName, $sport, $selectionId, $runnerName, $odds, $stake, $type, $betfairId, $motivation, $bucket, $leagueName, $leagueId, $fixtureId, $pMinute, $pPeriod]);

            echo json_encode(['status' => 'success', 'message' => 'Scommessa piazzata (' . $type . ')']);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function setMode()
    {
        $this->sendJsonHeader();
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
        $this->sendJsonHeader();
        $stmt = $this->db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
        $stmt->execute();
        $mode = $stmt->fetchColumn() ?: 'real';
        echo json_encode(['status' => 'success', 'mode' => $mode]);
    }

    public function autoProcess()
    {
        $this->sendJsonHeader();

        // Throttling: 1 analisi ogni 120 secondi (Ottimizzazione Gemini)
        $cooldownFile = Config::DATA_PATH . 'gianik_gemini_cooldown.txt';
        $lastRun = file_exists($cooldownFile) ? (int) file_get_contents($cooldownFile) : 0;
        if (time() - $lastRun < 120) {
            echo json_encode(['status' => 'success', 'message' => 'GiaNik in cooldown']);
            return;
        }
        file_put_contents($cooldownFile, time());

        $results = [
            'found_on_betfair' => 0,
            'skipped_already_bet' => 0,
            'scanned' => 0,
            'new_bets' => 0,
            'errors' => []
        ];
        try {
            // Sincronizza lo stato reale prima di procedere
            $this->syncWithBetfair();

            // Check operational mode
            $stmtMode = $this->db->prepare("SELECT value FROM system_state WHERE key = 'operational_mode'");
            $stmtMode->execute();
            $globalMode = $stmtMode->fetchColumn() ?: 'real';

            // Restricted to Soccer (ID 1)
            $eventTypeIds = ['1'];
            $liveEventsRes = $this->bf->getLiveEvents($eventTypeIds);
            $events = $liveEventsRes['result'] ?? [];

            $apiLiveRes = $this->footballData->getLiveMatches();
            $apiLiveFixtures = $apiLiveRes['response'] ?? [];

            // Pre-fetch Today and Tomorrow for better matching without redundant API calls
            $apiTodayRes = $this->footballData->getFixturesByDate(date('Y-m-d'));
            $apiTomorrowRes = $this->footballData->getFixturesByDate(date('Y-m-d', strtotime('+1 day')));

            $apiFullFixtures = array_merge(
                $apiLiveFixtures,
                $apiTodayRes['response'] ?? [],
                $apiTomorrowRes['response'] ?? []
            );

            if (empty($events)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessun evento live']);
                return;
            }

            $results['found_on_betfair'] = count($events);

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

            $stmtPending = $this->db->prepare("SELECT event_name, fixture_id FROM bets WHERE status = 'pending'");
            $stmtPending->execute();
            $pendingBets = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
            $pendingEventNames = array_column($pendingBets, 'event_name');
            $pendingFixtureIds = array_filter(array_column($pendingBets, 'fixture_id'));

            // Pre-normalize pending names for faster lookup
            $normPendingNames = array_map([$this, 'normalizeEventName'], $pendingEventNames);

            $stmtPendingMarkets = $this->db->prepare("SELECT DISTINCT market_id FROM bets WHERE status = 'pending'");
            $stmtPendingMarkets->execute();
            $pendingMarketIds = $stmtPendingMarkets->fetchAll(PDO::FETCH_COLUMN);

            // Recuperiamo il conteggio delle scommesse per oggi per ogni match (per limitare ingressi multipli)
            $stmtCount = $this->db->prepare("SELECT event_name, COUNT(*) as cnt FROM bets WHERE created_at >= date('now') GROUP BY event_name");
            $stmtCount->execute();
            $matchBetCounts = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR);

            // Fetch dynamic config for GiaNik
            $strategyPrompt = $this->db->query("SELECT value FROM system_state WHERE key = 'strategy_prompt'")->fetchColumn();
            $stakeMode = $this->db->query("SELECT value FROM system_state WHERE key = 'stake_mode'")->fetchColumn() ?: 'kelly';
            $stakeValue = (float)($this->db->query("SELECT value FROM system_state WHERE key = 'stake_value'")->fetchColumn() ?: 0.15);
            $minConfidence = (int)($this->db->query("SELECT value FROM system_state WHERE key = 'min_confidence'")->fetchColumn() ?: 80);
            $minStake = (float)($this->db->query("SELECT value FROM system_state WHERE key = 'min_stake'")->fetchColumn() ?: 2.00);

            // Fetch correct balance for Gemini based on operational mode
            $activeBalance = ['available' => 0, 'total' => 0];
            if ($globalMode === 'real') {
                $fundsData = $this->bf->getFunds();
                $funds = $fundsData['result'] ?? $fundsData;
                $activeBalance['available'] = (float) ($funds['availableToBetBalance'] ?? 0);
                $activeBalance['total'] = $activeBalance['available'] + abs((float) ($funds['exposure'] ?? 0));
            } else {
                $vBal = $this->getVirtualBalance();
                $activeBalance['available'] = $vBal['available'];
                $activeBalance['total'] = $vBal['total'];
            }

            // --- WORKING BANKROLL FOR BATCH PROCESSING ---
            $workingTotalBankroll = $activeBalance['total'];
            $workingAvailableBalance = $activeBalance['available'];

            $batchEvents = [];
            $batchMetadata = [];

            // Collect candidates for batch processing
            foreach ($eventMarketsMap as $eid => $catalogues) {
                $mainEvent = $catalogues[0];
                $eventName = $mainEvent['event']['name'];

                // 1. Single Concurrent Bet check
                $normCurrent = $this->normalizeEventName($eventName);
                if (in_array($eventName, $pendingEventNames) || in_array($normCurrent, $normPendingNames)) {
                    continue;
                }

                // 2. Daily Limit check
                $totalBetsForMatch = $matchBetCounts[$eventName] ?? 0;
                if ($totalBetsForMatch >= 4) {
                    continue;
                }

                try {
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

                    if (empty($event['markets'])) continue;

                    // Enrichment
                    $firstMarketId = $event['markets'][0]['marketId'] ?? null;
                    $firstBook = $booksMap[$firstMarketId] ?? null;
                    $apiData = $this->enrichWithApiData($event['event'], $event['sport'], $apiFullFixtures, $event['competition'], $firstBook);
                    $event['api_football'] = $apiData;

                    // Always define currentMin if apiData is available to avoid warnings in later priority/shock checks
                    $currentMin = (int)($apiData['live']['live_status']['elapsed_minutes'] ?? 0);

                    // HEURISTIC PRIORITY FILTER: Only analyze if Intensity Index is high or there was a recent goal
                    $intensity = $this->getLastIntensityBadge($apiData['fixture']['id'] ?? null);
                    $isPriority = false;
                    if ($intensity && $intensity['val'] >= 1.2) $isPriority = true;

                    // Check for recent goals (last 5 mins)
                    if (!$isPriority && !empty($apiData['events'])) {
                        foreach ($apiData['events'] as $ev) {
                            if ($ev['type'] === 'Goal' && ($currentMin - (int)$ev['time']['elapsed']) <= 5) {
                                $isPriority = true;
                                break;
                            }
                        }
                    }

                    if (!$isPriority) continue;

                    // Deep Context & Performance
                    if (!empty($apiData['fixture'])) {
                        $fix = $apiData['fixture'];
                        $deepCtx = $this->intelligence->getDeepContext($fix['id'], $fix['team_home_id'], $fix['team_away_id'], $fix['league_id']);
                        $event['deep_context'] = $this->summarizeDeepContext($deepCtx);

                        $teams = $this->intelligence->parseTeams($event['event']);
                        $event['performance_metrics'] = $this->intelligence->getPerformanceContext(
                            $teams['home'] ?? '', $teams['away'] ?? '',
                            '1X2', $event['competition'], $fix['league_id'] ?? null
                        );

                        // Gatekeeper check
                        if ($fix['league_id'] && $this->checkGatekeeper($fix['league_id'])) continue;

                        // Shock check (Circuit Breaker)
                        $isShock = false;
                        foreach (($apiData['events'] ?? []) as $ev) {
                            $type = $ev['type'] ?? '';
                            if (($type === 'Goal' || ($type === 'Card' && strpos(strtolower($ev['detail'] ?? ''), 'red') !== false)) && ($currentMin - (int)$ev['time']['elapsed']) <= 4) {
                                $isShock = true; break;
                            }
                        }
                        if ($isShock) continue;
                    }

                    $event['ai_lessons'] = $this->getRecentLessons($apiData);

                    $batchEvents[] = $event;
                    $batchMetadata[$event['event']] = [
                        'api_data' => $apiData,
                        'books_map' => $booksMap,
                        'markets' => $event['markets']
                    ];

                    if (count($batchEvents) >= 3) break; // Batch cap
                } catch (\Throwable $ex) {
                    $results['errors'][] = $ex->getMessage();
                }
            }

            if (!empty($batchEvents)) {
                $gemini = new GeminiService();
                $predictionRaw = $gemini->analyzeBatch($batchEvents, [
                    'is_gianik' => true,
                    'available_balance' => $activeBalance['available'],
                    'current_portfolio' => $activeBalance['total'],
                    'custom_prompt' => $strategyPrompt
                ]);

                $batchAnalysis = json_decode($predictionRaw, true);
                if (!is_array($batchAnalysis)) {
                    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                        $batchAnalysis = json_decode($matches[1], true);
                    }
                }

                if (is_array($batchAnalysis)) {
                    foreach ($batchAnalysis as $analysis) {
                        $eventName = $analysis['eventName'] ?? '';
                        if (!isset($batchMetadata[$eventName])) continue;
                        $meta = $batchMetadata[$eventName];

                        // Verifica confidenza minima (hard cap dinamico, default 80%)
                        if (!empty($analysis['marketId']) && !empty($analysis['advice']) && ($analysis['confidence'] ?? 0) >= $minConfidence) {

                            $selectedMarket = null;
                            foreach ($meta['markets'] as $m) {
                                if ($m['marketId'] === $analysis['marketId']) {
                                    $selectedMarket = $m; break;
                                }
                            }
                            if (!$selectedMarket || in_array($analysis['marketId'], $pendingMarketIds)) continue;

                            // --- CALCOLO STAKE (Dinamico) ---
                            $decision = MoneyManagementService::calculateStake(
                                $workingTotalBankroll,
                                (float)$analysis['odds'],
                                (float)$analysis['confidence'],
                                $stakeMode,
                                $stakeValue,
                                $minStake
                            );

                            if (!$decision['is_value_bet'] || $decision['stake'] < max(2.00, $minStake)) {
                                continue;
                            }

                            $stake = $decision['stake'];

                            // Verifica disponibilità liquida effettiva nel working balance
                            if ($stake > $workingAvailableBalance) {
                                $stake = $workingAvailableBalance;
                            }

                            if ($stake < max(2.00, $minStake)) continue;

                            $runners = array_map(fn($r) => ['runnerName' => $r['name'], 'selectionId' => $r['selectionId']], $selectedMarket['runners']);
                            $selectionId = $this->bf->mapAdviceToSelection($analysis['advice'], $runners);

                            if ($selectionId) {
                                if (($analysis['odds'] ?? 0) < Config::MIN_BETFAIR_ODDS) $analysis['odds'] = Config::MIN_BETFAIR_ODDS;

                                $betfairId = null;
                                if ($globalMode === 'real') {
                                    $res = $this->bf->placeBet($analysis['marketId'], $selectionId, $analysis['odds'], $stake);
                                    if (($res['status'] ?? '') === 'SUCCESS') $betfairId = $res['instructionReports'][0]['betId'] ?? null;
                                    else continue;
                                }

                                // Update working bankroll for the next iteration in the batch
                                $workingAvailableBalance -= $stake;
                                // $workingTotalBankroll remains same for the loop as it's the reference for Kelly during the scan,
                                // but we could also decrease it to be even more conservative.
                                // Gianik said: "il secondo deve calcolare la % sugli 80€ rimanenti, non su 100€"
                                $workingTotalBankroll -= $stake;

                                $bucket = $this->getOddsBucket($analysis['odds']);
                                $motivation = $this->extractMotivation($analysis, $predictionRaw, ['api_football' => $meta['api_data']]);
                                $fixtureId = $meta['api_data']['fixture']['id'] ?? null;

                                $stmtInsert = $this->db->prepare("INSERT INTO bets (market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, type, betfair_id, motivation, bucket, league, league_id, fixture_id, placed_at_minute, placed_at_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmtInsert->execute([
                                    $analysis['marketId'], $selectedMarket['marketName'], $eventName, 'Soccer',
                                    $selectionId, $analysis['advice'], $analysis['odds'], $stake,
                                    $globalMode, $betfairId, $motivation, $bucket,
                                    $meta['api_data']['fixture']['league_name'] ?? 'Unknown',
                                    $meta['api_data']['fixture']['league_id'] ?? null,
                                    $fixtureId,
                                    (int)($meta['api_data']['live']['live_status']['elapsed_minutes'] ?? 0),
                                    ($meta['api_data']['live']['live_status']['short'] ?? 'NS') === '1H' ? '1H' : '2H'
                                ]);
                                $results['new_bets']++;
                            }
                        }
                    }
                }
            }

            $this->settleBets();
            echo json_encode(['status' => 'success', 'results' => $results]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function normalizeEventName($name)
    {
        if (!$name) return '';
        $name = strtolower($name);
        // Remove common scores like "1-0", "2 - 1"
        $name = preg_replace('/\d+\s*[-:]\s*\d+/', ' ', $name);
        // Remove connectors
        $name = preg_replace('/\b(v|vs|@|-|\/)\b/', ' ', $name);
        // Remove non-alphanumeric and collapse spaces
        $name = preg_replace('/[^a-z0-9 ]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function isBetAlreadyPendingForMatch($fixtureId, $eventName)
    {
        // 1. Check by fixture_id if available (High Precision)
        if ($fixtureId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM bets WHERE status = 'pending' AND fixture_id = ?");
            $stmt->execute([$fixtureId]);
            if ((int)$stmt->fetchColumn() > 0) return true;
        }

        // 2. Fallback to Normalized Event Name (High Recall)
        $normCurrent = $this->normalizeEventName($eventName);
        if (empty($normCurrent)) return false;

        $stmt = $this->db->prepare("SELECT event_name FROM bets WHERE status = 'pending'");
        $stmt->execute();
        $pendingNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($pendingNames as $pName) {
            if ($this->normalizeEventName($pName) === $normCurrent) {
                return true;
            }
        }

        return false;
    }

    public function settleBets()
    {
        $results = ['wins' => 0, 'losses' => 0];
        try {
            // Recupera tutte le scommesse pendenti (virtuali e reali)
            // Usiamo un intervallo più breve per GiaNik per maggiore reattività
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'pending' AND created_at < datetime('now', '-2 minutes')");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pending))
                return $results;

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
                            foreach ($pending as $bet) {
                                if ($bet['market_id'] === $mb['marketId']) {
                                    $isWin = ($winnerSelectionId == $bet['selection_id']);
                                    $status = $isWin ? 'won' : 'lost';
                                    $profit = $isWin ? ($bet['stake'] * ($bet['odds'] - 1)) : -$bet['stake'];
                                    $commission = $isWin ? ($profit * 0.05) : 0; // Stima 5% su vincita lorda
                                    $netProfit = $profit - $commission;

                                    $needsAnalysis = (!$isWin) ? 1 : 0;

                                    $this->db->prepare("UPDATE bets SET status = ?, profit = ?, commission = ?, settled_at = CURRENT_TIMESTAMP, needs_analysis = ? WHERE id = ?")
                                        ->execute([$status, $profit, $commission, $needsAnalysis, $bet['id']]);

                                    // Update Performance Metrics (Learning)
                                    $fullBet = array_merge($bet, [
                                        'status' => $status,
                                        'profit' => $profit,
                                        'commission' => $commission
                                    ]);
                                    $this->intelligence->learnFromBet($fullBet);

                                    if ($isWin) {
                                        $results['wins']++;
                                    } else {
                                        $results['losses']++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("GiaNik Settlement Error: " . $e->getMessage());
        }
        return $results;
    }

    private function enrichWithApiData($bfEventName, $sport, $preFetchedLive = null, $competitionName = null, $bfMarketBook = null, $betfairEventId = null)
    {
        // Try to extract countryCode and startTime from MarketBook if available
        $countryCode = null;
        if ($bfMarketBook && isset($bfMarketBook['marketDefinition']['countryCode'])) {
            $countryCode = $bfMarketBook['marketDefinition']['countryCode'];
        }

        $startTime = null;
        if ($bfMarketBook && isset($bfMarketBook['marketDefinition']['marketTime'])) {
            $startTime = $bfMarketBook['marketDefinition']['marketTime'];
        }

        // Use competitionName as a fallback for country/league context if needed,
        // but findMatchingFixture primarily uses countryCode and startTime.
        $apiMatch = $this->findMatchingFixture($bfEventName, $sport, $preFetchedLive, $countryCode, $startTime, $betfairEventId ?: ($bfMarketBook['marketDefinition']['eventId'] ?? null));

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
        $statusFromApi = $apiMatch['fixture']['status']['short'] ?? 'NS';

        $details = $this->footballData->getFixtureDetails($fid);

        // Use the most "live" status available
        $status = $statusFromApi;
        if ($details && in_array($details['status_short'], ['1H', 'HT', '2H', 'ET', 'P', 'BT', 'FT'])) {
            $status = $details['status_short'];
        }

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

        // 🚀 MOMENTUM (Derivata prima delle statistiche)
        $momentum = $this->handleMomentum($fid, $stats, $details['elapsed'] ?? 0);

        return [
            'fixture' => $details,
            'live' => $liveData,  // ← DATI LIVE ESPLICITI
            'statistics' => $stats,  // ← STATISTICS LIVE (shots, possession, corners, ecc.)
            'momentum' => $momentum, // ← MOMENTUM (Variazione ultimi 10 min)
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
            'TR' => ['Turkey', 'Turkiye'],
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
            'IL' => 'Israel',
            'CY' => 'Cyprus',
            'US' => 'USA',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'CN' => 'China',
            'KR' => 'South Korea',
            'SA' => 'Saudi Arabia',
            'AE' => 'UAE',
            'QA' => 'Qatar',
            'IE' => 'Ireland',
            'DZ' => 'Algeria',
            'MA' => 'Morocco',
            'TN' => 'Tunisia',
            'EG' => 'Egypt',
            'IN' => 'India',
            'AZ' => 'Azerbaijan',
            'MT' => 'Malta',
            'IS' => 'Iceland',
            'CS' => 'Serbia',
            'HK' => 'Hong Kong',
            'TH' => 'Thailand',
            'SG' => 'Singapore',
            'NZ' => 'New Zealand',
            'CA' => 'Canada',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'JO' => 'Jordan',
            'LB' => 'Lebanon',
            'SY' => 'Syria',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'UZ' => 'Uzbekistan',
            'KZ' => 'Kazakhstan',
            'GE' => 'Georgia',
            'AM' => 'Armenia',
            'MD' => 'Moldova',
            'EE' => 'Estonia',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LI' => 'Liechtenstein',
            'MC' => 'Monaco',
            'AD' => 'Andorra',
            'SM' => 'San Marino',
            'GI' => 'Gibraltar',
            'FO' => 'Faroe Islands',
            'ME' => 'Montenegro',
            'MK' => 'North Macedonia',
            'AL' => 'Albania',
            'XK' => 'Kosovo',
            'CY' => 'Cyprus',
        ];
        return $map[strtoupper($countryCode)] ?? null;
    }

    /**
     * Extracts a robust motivation from Gemini output
     */
    private function extractMotivation($analysis, $predictionRaw, $event)
    {
        // 1. Priority: JSON motivation field (and variants)
        $motivation = $analysis['motivation'] ?? ($analysis['reasoning'] ?? ($analysis['logic'] ?? ($analysis['analysis'] ?? '')));

        // 2. Fallback: Narrative text after JSON (only if not raw JSON)
        if (empty(trim($motivation))) {
            $cleanedRaw = trim(preg_replace('/```json[\s\S]*?(?:```|$)/', '', $predictionRaw));
            if (!empty($cleanedRaw) && strpos($predictionRaw, '{') !== 0) {
                $motivation = $cleanedRaw;
            }
        }

        // 3. Final Fallback: Auto-generated summary
        if (empty(trim($motivation))) {
            $sData = $event['api_football']['live']['live_score'] ?? null;
            $score = is_array($sData) ? (($sData['home'] ?? 0) . '-' . ($sData['away'] ?? 0)) : '0-0';
            $sentiment = $analysis['sentiment'] ?? 'Neutral';
            $confidence = $analysis['confidence'] ?? 0;
            $advice = $analysis['advice'] ?? 'N/A';
            $motivation = "Analisi GiaNik: Operazione suggerita su $advice con confidenza del $confidence%. Il match si trova sul punteggio di $score. Sentiment di mercato: $sentiment. L'analisi dei volumi e dei dati live suggerisce questa operazione come la più bilanciata.";
        }

        return trim($motivation);
    }

    /**
     * Calcola lo stake dinamico basato sulla fiducia dell'AI e sul bankroll totale.
     * DEPRECATO: Usare MoneyManagementService::calculateOptimalStake
     */
    private function calculateDynamicStake($confidence, $bankroll, $odds = 1.50)
    {
        $decision = MoneyManagementService::calculateOptimalStake($bankroll, $odds, $confidence, Config::KELLY_MULTIPLIER_GIANIK);
        return $decision['stake'];
    }

    private function handleMomentum($fixtureId, $currentStats, $elapsed = 0)
    {
        if (!$fixtureId || empty($currentStats)) return null;
        $elapsed = max(1, (int)$elapsed);

        // 1. Estrai dati chiave
        $data = [
            'home_shots' => 0, 'away_shots' => 0,
            'home_corners' => 0, 'away_corners' => 0,
            'home_possession' => 0, 'away_possession' => 0,
            'dangerous_attacks_home' => 0, 'dangerous_attacks_away' => 0
        ];

        foreach ($currentStats as $index => $teamStats) {
            $prefix = ($index === 0) ? 'home_' : 'away_';
            $stats = $teamStats['stats_json'] ?? $teamStats['statistics'] ?? [];
            foreach ($stats as $s) {
                $val = (int)str_replace(['%', ' '], '', (string)$s['value']);
                if ($s['type'] === 'Total Shots') $data[$prefix . 'shots'] = $val;
                if ($s['type'] === 'Corner Kicks') $data[$prefix . 'corners'] = $val;
                if ($s['type'] === 'Ball Possession') $data[$prefix . 'possession'] = $val;
                if ($s['type'] === 'Dangerous Attacks') {
                    if ($index === 0) $data['dangerous_attacks_home'] = $val;
                    else $data['dangerous_attacks_away'] = $val;
                }
            }
        }

        // Salva snapshot strutturato
        $this->db->prepare("INSERT OR REPLACE INTO match_snapshots
            (fixture_id, minute, home_shots, away_shots, home_corners, away_corners, home_possession, away_possession, dangerous_attacks_home, dangerous_attacks_away, stats_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $fixtureId, $elapsed,
                $data['home_shots'], $data['away_shots'],
                $data['home_corners'], $data['away_corners'],
                $data['home_possession'], $data['away_possession'],
                $data['dangerous_attacks_home'], $data['dangerous_attacks_away'],
                json_encode($currentStats)
            ]);

        // 2. Recupera snapshot di circa 10 minuti fa (finestra 8-15 min)
        $stmt = $this->db->prepare("SELECT * FROM match_snapshots WHERE fixture_id = ? AND minute <= ? AND minute >= ? ORDER BY minute DESC LIMIT 1");
        $stmt->execute([$fixtureId, $elapsed - 8, $elapsed - 15]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old) return "Momentum: Calcolo in corso (raccolta dati)...";

        $deltaMin = max(1, $elapsed - $old['minute']);
        $momentum = "INTENSITY INDEX (Ultimi $deltaMin min):\n";

        foreach ([0, 1] as $index) {
            $prefix = ($index === 0) ? 'home_' : 'away_';
            $sideName = ($index === 0) ? "Casa" : "Ospite";

            $currShots = $data[$prefix . 'shots'];
            $oldShots = (int)$old[$prefix . 'shots'];
            $currCorners = $data[$prefix . 'corners'];
            $oldCorners = (int)$old[$prefix . 'corners'];

            $shotsLast = $currShots - $oldShots;
            $cornersLast = $currCorners - $oldCorners;

            // Intensity Formula: Intensity = (ShotsLast + CornersLast * 0.5) / MatchAveragePerDelta
            $matchAvgPer10 = (($currShots + $currCorners * 0.5) / $elapsed) * 10;
            $matchAvgPerDelta = $matchAvgPer10 * ($deltaMin / 10);

            $intensity = ($matchAvgPerDelta > 0) ? ($shotsLast + $cornersLast * 0.5) / $matchAvgPerDelta : 0;

            $status = "Stabile";
            if ($intensity > 1.5) $status = "⚡ ALTA (Pressione in aumento)";
            elseif ($intensity < 0.5) $status = "❄️ BASSA (Fase di stanca)";

            $momentum .= "- $sideName: " . round($intensity, 2) . "x media match ($status). [Shots: +$shotsLast, Corners: +$cornersLast]\n";
        }

        // Pulizia snapshot vecchi (> 24h)
        $this->db->exec("DELETE FROM match_snapshots WHERE timestamp < datetime('now', '-24 hours')");

        return $momentum;
    }

    private function getRecentLessons($apiData)
    {
        if (empty($apiData['fixture'])) return "";

        $homeId = $apiData['fixture']['team_home_id'] ?? null;
        $awayId = $apiData['fixture']['team_away_id'] ?? null;
        $leagueId = $apiData['fixture']['league_id'] ?? null;

        $stmt = $this->db->prepare("SELECT lesson_text FROM ai_lessons WHERE (entity_type = 'team' AND entity_id IN (?, ?)) OR (entity_type = 'league' AND entity_id = ?) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$homeId, $awayId, $leagueId]);
        $lessons = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($lessons)) return "";

        $text = "📚 LEZIONI DAI MATCH PASSATI:\n";
        foreach ($lessons as $l) {
            $text .= "- $l\n";
        }
        return $text;
    }

    private function getOddsBucket($odds)
    {
        return $this->intelligence->getOddsBucket($odds);
    }

    private function checkGatekeeper($leagueId)
    {
        if (!$leagueId) return null;

        $stmt = $this->db->prepare("SELECT roi, total_bets FROM performance_metrics WHERE context_type = 'LEAGUE' AND context_id = ?");
        $stmt->execute([strtoupper((string)$leagueId)]);
        $metric = $stmt->fetch(PDO::FETCH_ASSOC);

        // STOP LOSS RIGIDO: Se ROI < -15% su almeno 10 bet -> BLOCCO
        if ($metric && $metric['total_bets'] >= 10 && $metric['roi'] < -15.0) {
            return "BLOCCO GATEKEEPER: La lega $leagueId ha un ROI storico pessimo (" . round($metric['roi'], 1) . "%) su {$metric['total_bets']} scommesse.";
        }

        return null;
    }

    /**
     * Recupera l'ultimo badge di intensità per un match.
     * L'intensità viene calcolata pesando tiri (1.0) e corner (0.5) dell'ultimo intervallo (8-15 min)
     * rispetto alla media della partita.
     * Nota: Può restituire null se mancano i dati statistici (comune in campionati minori)
     * o se il match è appena iniziato (meno di 2 snapshot disponibili).
     */
    private function getLastIntensityBadge($fixtureId)
    {
        if (!$fixtureId) return null;

        $stmt2 = $this->db->prepare("SELECT * FROM match_snapshots WHERE fixture_id = ? ORDER BY minute DESC LIMIT 2");
        $stmt2->execute([$fixtureId]);
        $rows = $stmt2->fetchAll();

        if (count($rows) < 2) return null;

        $curr = $rows[0];
        $old = $rows[1];
        $deltaMin = $curr['minute'] - $old['minute'];
        if ($deltaMin <= 0) return null;

        $homeIntensity = 0;
        $awayIntensity = 0;

        // Calcolo rapido Intensità Casa
        $currH = $curr['home_shots'] + $curr['home_corners'] * 0.5;
        $oldH = $old['home_shots'] + $old['home_corners'] * 0.5;
        $avgH = ($currH / max(1, $curr['minute'])) * $deltaMin;
        if ($avgH > 0) $homeIntensity = ($currH - $oldH) / $avgH;

        // Calcolo rapido Intensità Ospite
        $currA = $curr['away_shots'] + $curr['away_corners'] * 0.5;
        $oldA = $old['away_shots'] + $old['away_corners'] * 0.5;
        $avgA = ($currA / max(1, $curr['minute'])) * $deltaMin;
        if ($avgA > 0) $awayIntensity = ($currA - $oldA) / $avgA;

        $maxInt = max($homeIntensity, $awayIntensity);

        $data = [
            'val' => round($maxInt, 1),
            'home' => round($homeIntensity, 1),
            'away' => round($awayIntensity, 1)
        ];

        if ($maxInt > 1.5) {
            $data['label'] = 'HIGH';
            $data['color'] = 'text-accent';
        } elseif ($maxInt > 1.1) {
            $data['label'] = 'MID';
            $data['color'] = 'text-indigo-400';
        } else {
            $data['label'] = 'STABLE';
            $data['color'] = 'text-slate-500';
        }

        return $data;
    }

    private function summarizeDeepContext($context)
    {
        if (!$context) return "Nessun dato storico profondo disponibile.";

        $summary = "";

        // Home summary
        $h = $context['home'] ?? [];
        $homeName = $h['standing']['team_name'] ?? 'Casa';
        $summary .= "CASA ({$homeName}): ";
        if (!empty($h['standing'])) {
            $summary .= "Pos: {$h['standing']['rank']} ({$h['standing']['points']} pt). ";
        }
        if (!empty($h['recent_matches'])) {
            $form = "";
            $goalsFor = 0; $goalsAgainst = 0;
            foreach ($h['recent_matches'] as $m) {
                $isHome = $m['team_home_id'] == ($h['standing']['team_id'] ?? null);
                $myScore = $isHome ? $m['score_home'] : $m['score_away'];
                $oppScore = $isHome ? $m['score_away'] : $m['score_home'];
                if ($myScore > $oppScore) $form .= "W";
                elseif ($myScore < $oppScore) $form .= "L";
                else $form .= "D";
                $goalsFor += $myScore;
                $goalsAgainst += $oppScore;
            }
            $avgF = round($goalsFor / count($h['recent_matches']), 1);
            $avgA = round($goalsAgainst / count($h['recent_matches']), 1);
            $summary .= "Forma: $form (Avg GF: $avgF, GA: $avgA). ";
        }

        // Away summary
        $a = $context['away'] ?? [];
        $awayName = $a['standing']['team_name'] ?? 'Trasferta';
        $summary .= "\nOSPITE ({$awayName}): ";
        if (!empty($a['standing'])) {
            $summary .= "Pos: {$a['standing']['rank']} ({$a['standing']['points']} pt). ";
        }
        if (!empty($a['recent_matches'])) {
            $form = "";
            $goalsFor = 0; $goalsAgainst = 0;
            foreach ($a['recent_matches'] as $m) {
                $isHome = $m['team_home_id'] == ($a['standing']['team_id'] ?? null);
                $myScore = $isHome ? $m['score_home'] : $m['score_away'];
                $oppScore = $isHome ? $m['score_away'] : $m['score_home'];
                if ($myScore > $oppScore) $form .= "W";
                elseif ($myScore < $oppScore) $form .= "L";
                else $form .= "D";
                $goalsFor += $myScore;
                $goalsAgainst += $oppScore;
            }
            $avgF = round($goalsFor / count($a['recent_matches']), 1);
            $avgA = round($goalsAgainst / count($a['recent_matches']), 1);
            $summary .= "Forma: $form (Avg GF: $avgF, GA: $avgA). ";
        }

        // H2H summary
        if (!empty($context['h2h']) && !empty($context['h2h']['h2h_json'])) {
            $h2h = $context['h2h']['h2h_json'];
            $hWins = 0; $aWins = 0; $draws = 0;
            $hId = $h['standing']['team_id'] ?? null;
            foreach (array_slice($h2h, 0, 5) as $m) {
                $gh = $m['goals']['home'];
                $ga = $m['goals']['away'];
                if ($m['teams']['home']['id'] == $hId) {
                    if ($gh > $ga) $hWins++; elseif ($gh < $ga) $aWins++; else $draws++;
                } else {
                    if ($ga > $gh) $hWins++; elseif ($ga < $gh) $aWins++; else $draws++;
                }
            }
            $summary .= "\nH2H (ultimi 5): $hWins Casa, $aWins Ospite, $draws Pareggi.";
        }

        return $summary;
    }

    private function findMatchingFixture($bfEventName, $sport, $preFetchedLive = null, $countryCode = null, $startTime = null, $betfairEventId = null)
    {
        $allFixtures = $preFetchedLive ?: [];

        // 1. Check existing mapping if we have a betfairEventId
        if ($betfairEventId) {
            $stmt = $this->db->prepare("SELECT fixture_id FROM match_mappings WHERE betfair_event_id = ?");
            $stmt->execute([$betfairEventId]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                // Check in the provided list
                foreach ($allFixtures as $f) {
                    if (($f['fixture']['id'] ?? null) == $existingId) {
                        return $f;
                    }
                }
            }
        }

        $mappedCountry = $this->getCountryMapping($countryCode);

        // Find in the provided list (which should be pre-fetched fully outside the loop)
        $match = $this->footballData->searchInFixtureList($bfEventName, $allFixtures, $mappedCountry, $startTime);

        // 2. If found, save the mapping for future use
        if ($match && $betfairEventId && isset($match['fixture']['id'])) {
            try {
                $this->db->prepare("INSERT OR IGNORE INTO match_mappings (betfair_event_id, fixture_id) VALUES (?, ?)")
                    ->execute([$betfairEventId, $match['fixture']['id']]);
            } catch (\Exception $e) {
                // Table might not exist yet, or other silent error
                error_log("Mapping save failed: " . $e->getMessage());
            }
        }

        return $match;
    }

    public function recentBets()
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        try {
            // Sincronizza prima di mostrare la sidebar
            $this->syncWithBetfair();
            // Persistence via Session (Senior approach: lighter and more reliable for UI state)
            if (isset($_GET['status']))
                $_SESSION['recent_bets_status'] = $_GET['status'];
            if (isset($_GET['type']))
                $_SESSION['recent_bets_type'] = $_GET['type'];

            $statusFilter = $_SESSION['recent_bets_status'] ?? 'pending';
            $typeFilter = 'real';

            // 1. Sport mapping
            $sportMapping = [
                'Soccer' => 'Calcio',
                'Football' => 'Calcio'
            ];

            // 2. Main query for bets
            $sql = "SELECT * FROM bets";
            $where = [];
            $params = [];

            // Restricted to Soccer/Football and Real bets only
            $where[] = "sport IN ('Soccer', 'Football')";
            $where[] = "type = 'real'";

            if ($statusFilter === 'won') {
                $where[] = "status = 'won'";
            } elseif ($statusFilter === 'lost') {
                $where[] = "status = 'lost'";
            } else {
                // Default or 'pending' filter: strictly pending and MATCHED
                $where[] = "status = 'pending'";
                $where[] = "(type = 'virtual' OR size_matched > 0)";
                $statusFilter = 'pending';
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }

            $sql .= " GROUP BY CASE
                WHEN betfair_id IS NOT NULL THEN (CASE WHEN betfair_id LIKE '1:%' THEN SUBSTR(betfair_id, 3) ELSE betfair_id END)
                ELSE (market_id || selection_id || CAST(stake AS TEXT) || status)
            END";
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
            $currentType = $typeFilter;
            $currentSport = 'all';
            $sports = []; // Dropdown removed from view

            require __DIR__ . '/../Views/partials/recent_bets_sidebar.php';
        } catch (\Throwable $e) {
            echo '<div class="text-danger p-2 text-[10px]">' . $e->getMessage() . '</div>';
        }
    }

    private function getVirtualBalance()
    {
        $vInit = Config::DEFAULT_INITIAL_BANKROLL;
        // Sum only VIRTUAL bets profit
        $vProf = (float) $this->db->query("SELECT SUM(profit - commission) FROM bets WHERE type = 'virtual' AND status IN ('won', 'lost') AND sport IN ('Soccer', 'Football')")->fetchColumn();
        // Sum only VIRTUAL bets exposure
        $vExp = (float) $this->db->query("SELECT SUM(stake) FROM bets WHERE type = 'virtual' AND status = 'pending' AND sport IN ('Soccer', 'Football')")->fetchColumn();
        return [
            'available' => ($vInit + $vProf) - $vExp,
            'exposure' => $vExp,
            'total' => $vInit + $vProf
        ];
    }

    public function learn()
    {
        $this->sendJsonHeader();
        try {
            // Analisi post-mortem delle scommesse perse
            $stmt = $this->db->prepare("SELECT * FROM bets WHERE status = 'lost' AND needs_analysis = 1 LIMIT 3");
            $stmt->execute();
            $lostBets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lostBets)) {
                echo json_encode(['status' => 'success', 'message' => 'Nessuna scommessa da analizzare.']);
                return;
            }

            $gemini = new GeminiService();
            $results = [];

            foreach ($lostBets as $bet) {
                $prompt = "Hai perso questa scommessa. Analizza il fallimento e trai una lezione tecnica per il futuro.\n\n" .
                    "SCOMMESSA:\n" .
                    "- Match: {$bet['event_name']}\n" .
                    "- Mercato: {$bet['market_name']}\n" .
                    "- Scelta: {$bet['runner_name']} @ {$bet['odds']}\n" .
                    "- Motivazione originale: {$bet['motivation']}\n\n" .
                    "COMPITO:\n" .
                    "1. Identifica perché hai sbagliato (es: momentum ignorato, bias sulla favorita, ecc).\n" .
                    "2. Scrivi una lezione breve (max 25 parole).\n" .
                    "3. Decidi se la lezione si applica al TEAM (es. 'Liverpool'), alla LEGA (es. 'Premier League') o è una STRATEGIA generale.\n\n" .
                    "RISPONDI SOLO IN JSON:\n" .
                    "{\n" .
                    "  \"lesson\": \"...\",\n" .
                    "  \"type\": \"team|league|strategy\",\n" .
                    "  \"entity_name\": \"Nome del Team o della Lega se applicabile\"\n" .
                    "}";

                $predictionRaw = $gemini->analyzeCustom($prompt);
                $lessonData = null;
                if (preg_match('/```json\s*([\s\S]*?)\s*```/', $predictionRaw, $matches)) {
                    $lessonData = json_decode($matches[1], true);
                } elseif (strpos($predictionRaw, '{') !== false) {
                    $lessonData = json_decode($predictionRaw, true);
                }

                if ($lessonData) {
                    $entityId = $lessonData['entity_name'] ?? null;
                    if ($lessonData['type'] === 'strategy') $entityId = 'GLOBAL';

                    $this->db->prepare("INSERT INTO ai_lessons (entity_type, entity_id, lesson_text, match_context) VALUES (?, ?, ?, ?)")
                        ->execute([$lessonData['type'], $entityId, $lessonData['lesson'], $bet['event_name']]);
                }

                $this->db->prepare("UPDATE bets SET needs_analysis = 0 WHERE id = ?")
                    ->execute([$bet['id']]);
                $results[] = $bet['id'];
            }

            echo json_encode(['status' => 'success', 'analyzed' => $results]);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
                $sizeMatched = (float)($o['sizeMatched'] ?? 0);
                $allBfOrders[$o['betId']] = [
                    'id' => $o['betId'],
                    'marketId' => $o['marketId'],
                    'eventId' => null,
                    'selectionId' => $o['selectionId'],
                    'odds' => $o['priceSize']['price'] ?? 0,
                    'stake' => $o['priceSize']['size'] ?? 0,
                    'sizeMatched' => $sizeMatched,
                    'status' => 'pending',
                    'profit' => 0,
                    'commission' => 0, // Commission is 0 for pending bets
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
                $stakeSettled = (float)($o['sizeSettled'] ?? 0);
                $allBfOrders[$o['betId']] = [
                    'id' => $o['betId'],
                    'marketId' => $o['marketId'],
                    'eventId' => $o['eventId'] ?? null,
                    'selectionId' => $o['selectionId'],
                    'odds' => $o['priceRequested'] ?? 0,
                    'stake' => $stakeSettled,
                    'sizeMatched' => $stakeSettled, // Settled implies fully matched (or what was available)
                    'status' => ($o['betOutcome'] === 'WIN' || $o['betOutcome'] === 'WON') ? 'won' : (($o['betOutcome'] === 'VOIDED' || $o['betOutcome'] === 'CANCELLED') ? 'cancelled' : 'lost'),
                    'profit' => (float) ($o['profit'] ?? 0),
                    'commission' => (float) ($o['commission'] ?? 0),
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
                        'marketProjection' => ['EVENT', 'COMPETITION', 'MARKET_DESCRIPTION', 'RUNNER_DESCRIPTION']
                    ]);
                    foreach ($catRes['result'] ?? [] as $cat) {
                        $runners = [];
                        foreach ($cat['runners'] as $r) {
                            $runners[$r['selectionId']] = $r['runnerName'];
                        }
                        $marketInfoMap[$cat['marketId']] = [
                            'event' => $cat['event']['name'] ?? null,
                            'market' => $cat['marketName'] ?? null,
                            'competition' => $cat['competition']['name'] ?? null,
                            'runners' => $runners
                        ];
                    }
                }
            }

            // 4. MIRRORING & UPDATE: Sincronizza lo stato locale con Betfair
            foreach ($allBfOrders as $betId => $o) {
                $info = $marketInfoMap[$o['marketId']] ?? null;

                // Priorità Nome Evento: 1. Catalogo, 2. ItemDesc (eventDesc), 3. EventMap
                $eventName = $info['event'] ?? ($o['eventName'] ?? ($eventNameMap[$o['eventId'] ?? ''] ?? null));
                $marketName = $info['market'] ?? ($o['marketName'] ?? null);
                $runnerName = $info['runners'][$o['selectionId']] ?? ($o['runnerName'] ?? null);
                $leagueName = $info['competition'] ?? null;

                $fixtureId = null;
                if ($eventName) {
                    $match = $this->findMatchingFixture($eventName, 'Soccer', null, null, null, $o['eventId'] ?? null);
                    if ($match) $fixtureId = $match['fixture']['id'] ?? null;
                }

                // Use gmdate to store in UTC (SQLite standard)
                $placedDate = isset($o['placedDate']) ? gmdate('Y-m-d H:i:s', strtotime($o['placedDate'])) : gmdate('Y-m-d H:i:s');

                // Verifica esistenza nel DB locale (Flessibile su prefisso 1:)
                $altBetId = (strpos($betId, '1:') === 0) ? substr($betId, 2) : '1:' . $betId;
                $stmt = $this->db->prepare("SELECT id FROM bets WHERE betfair_id = ? OR betfair_id = ?");
                $stmt->execute([$betId, $altBetId]);
                $dbId = $stmt->fetchColumn();

                if ($dbId) {
                    // Recupera dati esistenti per evitare di sovrascrivere con NULL o Unknown
                    $stmtExisting = $this->db->prepare("SELECT event_name, market_name, runner_name, league FROM bets WHERE id = ?");
                    $stmtExisting->execute([$dbId]);
                    $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);

                    $finalEventName = $eventName ?: ($existing['event_name'] ?? 'Unknown Event');
                    $finalMarketName = $marketName ?: ($existing['market_name'] ?? 'Unknown Market');
                    $finalRunnerName = $runnerName ?: ($existing['runner_name'] ?? 'Unknown Runner');
                    $finalLeagueName = $leagueName ?: ($existing['league'] ?? null);

                    // Update: aggiorna sempre per avere l'ultimo stato (profitto, commissioni, status, sizeMatched)
                    $stmtUpdate = $this->db->prepare("UPDATE bets SET status = :status, profit = :profit, commission = :commission, market_id = :market_id, market_name = :market_name, event_name = :event_name, runner_name = :runner_name, league = :league, league_id = COALESCE(league_id, :league_id), fixture_id = COALESCE(fixture_id, :fixture_id), created_at = :created_at, betfair_id = :betfair_id, size_matched = :size_matched WHERE id = :id");
                    $stmtUpdate->execute([
                        ':status' => $o['status'],
                        ':profit' => $o['profit'],
                        ':commission' => $o['commission'],
                        ':market_id' => $o['marketId'],
                        ':market_name' => $finalMarketName,
                        ':event_name' => $finalEventName,
                        ':runner_name' => $finalRunnerName,
                        ':league' => $finalLeagueName,
                        ':league_id' => $o['league_id'] ?? null,
                        ':fixture_id' => $fixtureId,
                        ':created_at' => $placedDate,
                        ':betfair_id' => $betId,
                        ':size_matched' => $o['sizeMatched'] ?? 0,
                        ':id' => $dbId
                    ]);

                    // Apprendimento automatico se la scommessa è conclusa e non ancora appresa
                    if (in_array($o['status'], ['won', 'lost'])) {
                        $stmtFull = $this->db->prepare("SELECT * FROM bets WHERE id = ?");
                        $stmtFull->execute([$dbId]);
                        $fullBet = $stmtFull->fetch(PDO::FETCH_ASSOC);
                        if ($fullBet && !$fullBet['is_learned']) {
                            $this->intelligence->learnFromBet($fullBet);
                        }
                    }
                } else {
                    // Import Automatico (se non esisteva)
                    $stmtInsert = $this->db->prepare("INSERT INTO bets 
                        (betfair_id, market_id, market_name, event_name, sport, selection_id, runner_name, odds, stake, size_matched, status, type, profit, commission, league, fixture_id, created_at, motivation)
                        VALUES (:betfair_id, :market_id, :market_name, :event_name, 'Soccer', :selection_id, :runner_name, :odds, :stake, :size_matched, :status, 'real', :profit, :commission, :league, :fixture_id, :created_at, 'Scommessa importata da Betfair Exchange')");
                    $stmtInsert->execute([
                        ':betfair_id' => $betId,
                        ':market_id' => $o['marketId'],
                        ':market_name' => $marketName ?: 'Unknown Market',
                        ':event_name' => $eventName ?: 'Unknown Event',
                        ':selection_id' => $o['selectionId'],
                        ':runner_name' => $runnerName ?: 'Unknown Runner',
                        ':odds' => $o['odds'],
                        ':stake' => $o['stake'],
                        ':size_matched' => $o['sizeMatched'] ?? 0,
                        ':status' => $o['status'],
                        ':profit' => $o['profit'],
                        ':commission' => $o['commission'],
                        ':league' => $leagueName,
                        ':fixture_id' => $fixtureId,
                        ':created_at' => $placedDate
                    ]);

                    // Apprendimento immediato se importata già conclusa
                    if (in_array($o['status'], ['won', 'lost'])) {
                        $newId = $this->db->lastInsertId();
                        $stmtFull = $this->db->prepare("SELECT * FROM bets WHERE id = ?");
                        $stmtFull->execute([$newId]);
                        $fullBet = $stmtFull->fetch(PDO::FETCH_ASSOC);
                        if ($fullBet) {
                            $this->intelligence->learnFromBet($fullBet);
                        }
                    }
                }
            }

            // 5. MIRRORING & DEDUPLICAZIONE
            // Pulizia Preventiva Duplicati locali per BetID (flessibile su prefisso 1:)
            // Utilizziamo una sottoquery ordinata per preservare le record con la motivazione tecnica più completa (Analisi GiaNik)
            $this->db->exec("DELETE FROM bets WHERE type = 'real' AND id NOT IN (
                SELECT id FROM (
                    SELECT id,
                           CASE
                               WHEN betfair_id IS NOT NULL THEN (CASE WHEN betfair_id LIKE '1:%' THEN SUBSTR(betfair_id, 3) ELSE betfair_id END)
                               ELSE (market_id || selection_id || CAST(stake AS TEXT))
                           END as group_id
                    FROM bets
                    WHERE type = 'real'
                    ORDER BY (CASE
                        WHEN (motivation IS NOT NULL AND motivation != '' AND motivation != 'Scommessa importata da Betfair Exchange' AND motivation NOT LIKE 'Analisi GiaNik%') THEN 0
                        WHEN motivation LIKE 'Analisi GiaNik%' THEN 1
                        WHEN (motivation = 'Scommessa importata da Betfair Exchange') THEN 2
                        ELSE 3 END) ASC, id ASC
                ) as sorted
                GROUP BY group_id
            )");

            // Mirroring selettivo: cancelliamo i pendenti locali solo se abbiamo una risposta valida da listCurrentOrders
            if (isset($currentRes['currentOrders'])) {
                $currentBfIds = [];
                foreach ($currentOrders as $o) {
                    $currentBfIds[] = $o['betId'];
                    $currentBfIds[] = (strpos($o['betId'], '1:') === 0) ? substr($o['betId'], 2) : '1:' . $o['betId'];
                }
                $currentBfIds = array_values(array_unique($currentBfIds));

                $sqlDelete = "DELETE FROM bets WHERE type = 'real' AND status = 'pending' AND created_at < datetime('now', '-2 minutes')";
                if (!empty($currentBfIds)) {
                    $placeholders = implode(',', array_fill(0, count($currentBfIds), '?'));
                    $sqlDelete .= " AND betfair_id NOT IN ($placeholders) AND betfair_id IS NOT NULL";
                    $stmtDelete = $this->db->prepare($sqlDelete);
                    $stmtDelete->execute($currentBfIds);
                } else {
                    // Se Betfair conferma che non ci sono ordini pendenti, svuotiamo i pendenti locali con ID
                    $this->db->exec($sqlDelete . " AND betfair_id IS NOT NULL");
                }
            }

            // Pulizia record manuali orfani senza ID Betfair (solo se vecchi)
            $this->db->exec("DELETE FROM bets WHERE type = 'real' AND betfair_id IS NULL AND status = 'pending' AND created_at < datetime('now', '-5 minutes')");

            // 6. Sincronizza Aggiustamento di Sistema (per allineamento Brain)
            $fundsData = $this->bf->getFunds();
            $funds = $fundsData['result'] ?? $fundsData;
            if (isset($funds['availableToBetBalance'])) {
                $actualTotal = (float)($funds['availableToBetBalance']) + abs((float)($funds['exposure'] ?? 0));
                $this->intelligence->syncSystemAdjustment($actualTotal);
            }

        } catch (\Throwable $e) {
            file_put_contents(\App\Config\Config::LOGS_PATH . 'gianik_sync_error.log', date('[Y-m-d H:i:s] ') . "Sync Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    private function getPortfolioStats($type = 'virtual', $actualTotal = null)
    {
        $sql = "SELECT profit, commission, stake, status, settled_at, created_at
                FROM bets
                WHERE status IN ('won', 'lost')
                AND type = ?
                AND sport IN ('Soccer', 'Football')";

        if ($type === 'real') {
            $sql .= " AND betfair_id IS NOT NULL";
        }

        $sql .= " ORDER BY COALESCE(settled_at, created_at) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStake = 0;
        $netProfit = 0;
        $wins = 0;
        $losses = 0;
        $history = [Config::DEFAULT_INITIAL_BANKROLL];
        $labels = ['START'];

        $currentBalance = Config::DEFAULT_INITIAL_BANKROLL;
        foreach ($bets as $b) {
            $totalStake += (float) ($b['stake'] ?? 0);
            $netProfit += ((float) ($b['profit'] ?? 0) - (float) ($b['commission'] ?? 0));
            if ($b['status'] === 'won')
                $wins++;
            else
                $losses++;

            $currentBalance += ((float) ($b['profit'] ?? 0) - (float) ($b['commission'] ?? 0));
            $history[] = round($currentBalance, 2);
            $dateSource = !empty($b['settled_at']) ? $b['settled_at'] : $b['created_at'];
            // Append ' UTC' to force strtotime to interpret DB time as UTC and convert to local PHP timezone
            $labels[] = date('d/m H:i', strtotime($dateSource . ' UTC'));
        }

        // Adjust stats for REAL mode to match actual balance growth from initial bankroll
        if ($type === 'real' && $actualTotal !== null) {
            $trackedNetProfit = $netProfit;
            $realNetProfit = (float)$actualTotal - Config::DEFAULT_INITIAL_BANKROLL;
            $offset = $realNetProfit - $trackedNetProfit;

            $netProfit = $realNetProfit;

            // Adjust history to start at initial and end at actual total, while keeping tracked changes
            $newHistory = [Config::DEFAULT_INITIAL_BANKROLL];
            $newLabels = ['START'];

            if (abs($offset) > 0.01) {
                $newHistory[] = round(Config::DEFAULT_INITIAL_BANKROLL + $offset, 2);
                $newLabels[] = 'ADJ';
            }

            // Re-apply tracked changes on top of the initial adjusted balance
            $runningBalance = Config::DEFAULT_INITIAL_BANKROLL + $offset;
            foreach ($bets as $b) {
                $runningBalance += ((float) ($b['profit'] ?? 0) - (float) ($b['commission'] ?? 0));
                $newHistory[] = round($runningBalance, 2);
                $dateSource = !empty($b['settled_at']) ? $b['settled_at'] : $b['created_at'];
                // Append ' UTC' to force strtotime to interpret DB time as UTC and convert to local PHP timezone
                $newLabels[] = date('d/m H:i', strtotime($dateSource . ' UTC'));
            }

            $history = $newHistory;
            $labels = $newLabels;
        }

        $totalBets = $wins + $losses;
        return [
            'wins' => $wins,
            'losses' => $losses,
            'total_bets' => $totalBets,
            'win_rate' => $totalBets > 0 ? round(($wins / $totalBets) * 100, 1) : 0,
            'net_profit' => $netProfit,
            'total_stake' => $totalStake,
            'roi' => $totalStake > 0 ? round(($netProfit / $totalStake) * 100, 2) : 0,
            'history' => $history,
            'labels' => $labels
        ];
    }
}
