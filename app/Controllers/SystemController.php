<?php
namespace App\Controllers;

use App\Config\Config;
use App\Services\Database;

class SystemController
{
    private function checkAuth()
    {
        // Workaround per server CGI/FPM dove PHP_AUTH_USER non viene popolato automaticamente
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                list($user, $pw) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                list($user, $pw) = explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)));
            } else {
                $user = '';
                $pw = '';
            }
        } else {
            $user = $_SERVER['PHP_AUTH_USER'];
            $pw = $_SERVER['PHP_AUTH_PW'];
        }

        $validUser = trim(Config::get('ADMIN_USER', 'admin'));
        $validPass = trim(Config::get('ADMIN_PASS', 'admin'));

        // Debug Log (Temporaneo se serve debuggare)
        // error_log("Auth Attempt: InputUser='$user' vs ValidUser='$validUser'");

        if ($user !== $validUser || $pw !== $validPass) {
            header('WWW-Authenticate: Basic realm="Scommetto Admin Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    public function getSettings()
    {
        // Settings are public read, but write protected
        echo json_encode(Config::getSettings());
    }

    public function updateSettings()
    {
        $this->checkAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $settings = Config::getSettings();

            if (isset($input['simulation_mode'])) {
                $settings['simulation_mode'] = (bool) $input['simulation_mode'];
            }
            if (isset($input['initial_bankroll'])) {
                $settings['initial_bankroll'] = (float) $input['initial_bankroll'];
            }
            if (isset($input['virtual_bookmaker_id'])) {
                $settings['virtual_bookmaker_id'] = (int) $input['virtual_bookmaker_id'];
            }

            file_put_contents(Config::SETTINGS_FILE, json_encode($settings));
            echo json_encode(['status' => 'success', 'settings' => $settings]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        }
    }

    public function viewSettings()
    {
        $this->checkAuth();
        require __DIR__ . '/../Views/partials/settings.php';
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
