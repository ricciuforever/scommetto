<?php
// app/Controllers/BetController.php

namespace App\Controllers;

use App\Models\Bet;

class BetController
{
    private $betModel;

    public function __construct()
    {
        $this->betModel = new Bet();
    }

    public function getHistory()
    {
        header('Content-Type: application/json');
        try {
            $db = \App\Services\Database::getInstance()->getConnection();
            $sql = "SELECT b.*, l.country_name as country, bk.name as bookmaker_name_full
                    FROM bets b
                    LEFT JOIN fixtures f ON b.fixture_id = f.id
                    LEFT JOIN leagues l ON f.league_id = l.id
                    LEFT JOIN bookmakers bk ON b.bookmaker_id = bk.id
                    ORDER BY b.timestamp DESC";
            $history = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode($history);
        } catch (\Throwable $e) {
            echo json_encode([]);
        }
    }

    public function placeBet()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['fixture_id'])) {
            echo json_encode(['error' => 'Invalid input']);
            return;
        }

        // Prevent duplicates
        if ($this->betModel->isPending($input['fixture_id'])) {
            echo json_encode(['status' => 'already_exists']);
            return;
        }

        $id = $this->betModel->create($input);

        echo json_encode(['status' => 'success', 'id' => $id]);
    }
}
