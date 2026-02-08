<?php
// app/Controllers/FixtureController.php

namespace App\Controllers;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\FootballApiService;

class FixtureController
{
    /**
     * Ritorna le partite per una lega e stagione (sincronizza se necessario)
     */
    public function list()
    {
        header('Content-Type: application/json');
        try {
            $leagueId = $_GET['league'] ?? null;
            $season = $_GET['season'] ?? null;

            if (!$leagueId || !$season) {
                echo json_encode(['response' => [], 'message' => 'Lega e Stagione richieste.']);
                return;
            }

            $model = new Fixture();

            // Sync on-demand (scadenza 24 ore per il tabellone completo)
            if ($model->needsLeagueRefresh($leagueId, $season, 24)) {
                $this->syncLeagueFixtures($leagueId, $season);
            }

            // Recuperiamo tutte le partite dal DB
            $db = \App\Services\Database::getInstance()->getConnection();
            $sql = "SELECT f.*, 
                           t1.name as home_name, t1.logo as home_logo,
                           t2.name as away_name, t2.logo as away_logo
                    FROM fixtures f
                    JOIN teams t1 ON f.team_home_id = t1.id
                    JOIN teams t2 ON f.team_away_id = t2.id
                    WHERE f.league_id = ? AND EXISTS (SELECT 1 FROM league_seasons ls WHERE ls.league_id = f.league_id AND ls.year = ?)
                    ORDER BY f.date ASC";

            // Nota: f.league_id è nel DB, ma dobbiamo filtrare anche per stagione se archiviato.
            // Attualmente la tabella fixtures non ha la colonna 'season' esplicita ma è legata alle league_seasons.
            // Aggiungiamo un filtro per data basato sulla stagione se necessario, o meglio, usiamo il league_id.

            $stmt = $db->prepare($sql);
            $stmt->execute([$leagueId, $season]);
            $fixtures = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['response' => $fixtures]);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincronizza tutte le partite di una lega/stagione
     */
    private function syncLeagueFixtures($leagueId, $season)
    {
        $api = new FootballApiService();
        $fixtureModel = new Fixture();
        $teamModel = new Team();

        $data = $api->fetchLeaguesFixtures($leagueId, $season);

        if (isset($data['response']) && is_array($data['response'])) {
            foreach ($data['response'] as $item) {
                // Assicuriamoci che i team esistano prima di salvare la fixture
                $teamModel->save($item['teams']['home']);
                $teamModel->save($item['teams']['away']);

                $fixtureModel->save($item);
            }
            $fixtureModel->touchLeagueSeason($leagueId, $season);
        }
    }
}
