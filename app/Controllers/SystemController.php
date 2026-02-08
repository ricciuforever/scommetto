<?php
namespace App\Controllers;

use App\Config\Config;
use App\Services\Database;

class SystemController
{
    private function checkAuth()
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? '';
        $pass = $_SERVER['PHP_AUTH_PW'] ?? '';

        $validUser = Config::get('ADMIN_USER', 'admin');
        $validPass = Config::get('ADMIN_PASS', 'admin');

        if ($user !== $validUser || $pass !== $validPass) {
            header('WWW-Authenticate: Basic realm="Scommetto Admin Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    public function getSettings()
    {
        // Settings are public read, but write protected
        $settings = json_decode(file_get_contents(Config::DATA_PATH . 'settings.json') ?: '{"simulation_mode":true}', true);
        echo json_encode([
            'simulation_mode' => $settings['simulation_mode'] ?? true,
            'initial_bankroll' => Config::INITIAL_BANKROLL
        ]);
    }

    public function updateSettings()
    {
        $this->checkAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['simulation_mode'])) {
            $settings = json_decode(file_get_contents(Config::DATA_PATH . 'settings.json') ?: '{}', true);
            $settings['simulation_mode'] = (bool) $input['simulation_mode'];
            file_put_contents(Config::DATA_PATH . 'settings.json', json_encode($settings));
            echo json_encode(['status' => 'success', 'simulation_mode' => $settings['simulation_mode']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        }
    }

    public function resetSimulation()
    {
        $this->checkAuth();
        // Resetta solo le scommesse simulate (quelle senza betfair_id)
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM bets WHERE betfair_id IS NULL");
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Simulation Reset']);
    }
}
