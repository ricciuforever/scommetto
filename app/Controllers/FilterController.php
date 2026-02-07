<?php
// app/Controllers/FilterController.php

namespace App\Controllers;

use App\Models\Country;
use App\Models\Bookmaker;
use App\Services\Database;
use App\Config\Config;
use PDO;

class FilterController
{
    public function getFilters()
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance()->getConnection();

            // Countries ordered by importance then alphabetical
            $important = ["'Italy'", "'England'", "'Spain'", "'Germany'", "'France'", "'Brazil'", "'Argentina'"];
            $importantSql = implode(',', $important);

            $countries = $db->query("
				SELECT *, 
				CASE WHEN name IN ($importantSql) THEN 0 ELSE 1 END as importance
				FROM countries 
				WHERE name != 'World'
				ORDER BY importance ASC, name ASC
			")->fetchAll(PDO::FETCH_ASSOC);

            // Bookmakers ordered by managed matches
            $bookmakers = $db->query("
                SELECT b.*, COUNT(fo.fixture_id) as managed_matches
                FROM bookmakers b
                LEFT JOIN fixture_odds fo ON b.id = fo.bookmaker_id
                GROUP BY b.id
                ORDER BY managed_matches DESC, b.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'countries' => $countries,
                'bookmakers' => $bookmakers
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
