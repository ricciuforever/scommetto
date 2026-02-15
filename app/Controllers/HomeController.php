<?php

namespace App\Controllers;

use App\GiaNik\GiaNikDatabase;
use App\Dio\DioDatabase;
use App\Config\Config;
use PDO;

class HomeController
{
    public function index()
    {
        $pageTitle = 'Scommetto.AI - Explanatory Home';

        // Fetch some basic stats for GiaNik
        $gianikDb = GiaNikDatabase::getInstance()->getConnection();
        $gianikStats = $gianikDb->query("
            SELECT
                COUNT(*) as total_bets,
                SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as wins,
                SUM(profit - commission) as net_profit
            FROM bets
            WHERE status IN ('won', 'lost')
        ")->fetch(PDO::FETCH_ASSOC);

        // Fetch some basic stats for Dio
        $dioDb = DioDatabase::getInstance()->getConnection();
        // Dio uses a more complex recalculation, but let's take current state
        $dioStats = $dioDb->query("
            SELECT
                COUNT(*) as total_bets,
                SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as wins,
                SUM(profit) as total_profit
            FROM bets
            WHERE status IN ('won', 'lost')
        ")->fetch(PDO::FETCH_ASSOC);

        require __DIR__ . '/../Views/home.php';
    }
}
