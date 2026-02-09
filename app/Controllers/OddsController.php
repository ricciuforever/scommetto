<?php
// app/Controllers/OddsController.php

namespace App\Controllers;

use App\Services\FootballApiService;
use App\Models\LiveOdds;
use App\Models\LiveBetType;

class OddsController
{
    /**
     * Ritorna le quote live per una partita.
     * Endpoint: /api/odds/live?fixture={id}
     */
    public function live()
    {
        header('Content-Type: application/json');
        try {
            $fixtureId = $_GET['fixture'] ?? null;
            if (!$fixtureId) {
                echo json_encode(['error' => 'Fixture ID richiesto']);
                return;
            }

            $model = new LiveOdds();
            $data = $model->get($fixtureId);

            // Live odds sono molto volatili (5-30 sec). Caching molto breve o always-fetch.
            // Strategia: Se last_updated < 10 secondi fa, usa cache. Altrimenti fetch.
            $isStale = true;
            if ($data) {
                $last = strtotime($data['last_updated']);
                if (time() - $last < 10) { // 10 secondi cache
                    $isStale = false;
                }
            }

            if ($isStale || !$data) {
                $api = new FootballApiService();
                $apiResult = $api->fetchLiveOdds(['fixture' => $fixtureId]);

                if (!empty($apiResult['response'])) {
                    $liveData = $apiResult['response'][0];
                    // Struttura response[0]: { fixture: {}, league: {}, teams: {}, status: {}, update: "", odds: [] }

                    // Salviamo
                    $toSave = [
                        'odds' => $liveData['odds'],
                        'status' => $liveData['status']
                    ];
                    $model->save($fixtureId, $toSave);

                    // Rileggiamo o usiamo
                    $data = [
                        'odds_json' => json_encode($liveData['odds']),
                        'status_json' => json_encode($liveData['status']),
                        'last_updated' => date('Y-m-d H:i:s')
                    ];
                }
            }

            if ($data) {
                echo json_encode([
                    'response' => [
                        'odds' => json_decode($data['odds_json'], true),
                        'status' => json_decode($data['status_json'] ?? '{}', true),
                        'last_updated' => $data['last_updated']
                    ]
                ]);
            } else {
                echo json_encode(['response' => []]);
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ritorna i tipi di scommessa live disponibili.
     * Endpoint: /api/odds/live/bets
     */
    public function liveBets()
    {
        header('Content-Type: application/json');
        try {
            $model = new LiveBetType();
            $all = $model->getAll();

            // Sincronizza se vuoto o vecchio (es. 24h)
            // Se vuoto:
            if (empty($all)) {
                $this->syncLiveBets($model);
                $all = $model->getAll();
            } else {
                // Check primo elemento data? LiveBetType non ha data update per riga specifica nel getAll solitamente, ma tabella sì.
                // Per semplicità, facciamo sync on demand via parametro o job, oppure TTL semplice se tabella ha timestamp.
                // Qui assumiamo che se c'è, è buono per ora. 
                // Oppure check random row timestamp?
                // Facciamo: se $_GET['refresh'] esiste force sync.
                if (isset($_GET['refresh'])) {
                    $this->syncLiveBets($model);
                    $all = $model->getAll();
                }
            }

            echo json_encode(['response' => $all]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function syncLiveBets(LiveBetType $model)
    {
        $api = new FootballApiService();
        $apiResult = $api->fetchLiveOddsBets();

        if (!empty($apiResult['response'])) {
            foreach ($apiResult['response'] as $bet) {
                // { id: 1, name: "..." }
                $model->save($bet);
            }
        }
    }


    /**
     * Ritorna le quote pre-match per una partita.
     * Endpoint: /api/odds?fixture={id}&bookmaker={id}&bet={id}
     */
    public function prematch()
    {
        header('Content-Type: application/json');
        try {
            $fixtureId = $_GET['fixture'] ?? null;
            if (!$fixtureId) {
                echo json_encode(['error' => 'Fixture ID richiesto']);
                return;
            }

            // Optional filters
            $bookmakerId = $_GET['bookmaker'] ?? null;
            $betId = $_GET['bet'] ?? null;

            $model = new \App\Models\FixtureOdds();
            $data = $model->getByFixture($fixtureId);

            // Filter in memory if needed, or if empty fetch from API
            // Check staleness? Prematch odds updated every 3 hours.
            // Check last_updated of first row if exists
            $isStale = true;
            if (!empty($data)) {
                $last = strtotime($data[0]['last_updated']);
                if (time() - $last < 10800) { // 3 hours = 10800 seconds
                    $isStale = false;
                }
            }

            if ($isStale || empty($data)) {
                $api = new FootballApiService();
                // We fetch ALL odds for the fixture to cache them
                $apiResult = $api->fetchOdds(['fixture' => $fixtureId]);

                if (!empty($apiResult['response'])) {
                    $oddsData = $apiResult['response'][0];
                    // Structure: { fixture: {}, league: {}, update: "", bookmakers: [ { id, name, bets: [ { id, name, values: [] } ] } ] }

                    foreach ($oddsData['bookmakers'] as $bk) {
                        // Save Bookmaker if not exists?
                        (new \App\Models\Bookmaker())->save(['id' => $bk['id'], 'name' => $bk['name']]);

                        foreach ($bk['bets'] as $bet) {
                            // Save BetType if not exists?
                            (new \App\Models\BetType())->save(['id' => $bet['id'], 'name' => $bet['name']]);

                            // Save Odds
                            $model->save($fixtureId, $bk['id'], $bet['id'], $bet['values']);
                        }
                    }
                    // Re-fetch
                    $data = $model->getByFixture($fixtureId);
                }
            }

            // Filter data if params present
            if ($bookmakerId || $betId) {
                $data = array_filter($data, function ($row) use ($bookmakerId, $betId) {
                    if ($bookmakerId && $row['bookmaker_id'] != $bookmakerId)
                        return false;
                    if ($betId && $row['bet_id'] != $betId)
                        return false;
                    return true;
                });
                $data = array_values($data);
            }

            // Decode JSON for output
            foreach ($data as &$row) {
                $row['values'] = json_decode($row['odds_json'], true);
                unset($row['odds_json']);
            }

            echo json_encode(['response' => $data]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ritorna la lista dei bookmaker supportati
     */
    public function bookmakers()
    {
        header('Content-Type: application/json');
        try {
            $model = new \App\Models\Bookmaker();
            $all = $model->getAll();

            if (empty($all) || isset($_GET['refresh'])) {
                $api = new FootballApiService();
                $apiResult = $api->fetchBookmakers();
                if (!empty($apiResult['response'])) {
                    foreach ($apiResult['response'] as $bk) {
                        $model->save($bk);
                    }
                    $all = $model->getAll();
                }
            }
            echo json_encode(['response' => $all]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ritorna i tipi di scommessa pre-match
     */
    public function bets()
    {
        header('Content-Type: application/json');
        try {
            $model = new \App\Models\BetType();
            $all = $model->getAll();

            if (empty($all) || isset($_GET['refresh'])) {
                $api = new FootballApiService();
                $apiResult = $api->fetchBets();
                if (!empty($apiResult['response'])) {
                    foreach ($apiResult['response'] as $bt) {
                        $model->save($bt);
                    }
                    $all = $model->getAll();
                }
            }
            echo json_encode(['response' => $all]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
