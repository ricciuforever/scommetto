<?php

namespace App\Controllers;

use App\Config\Config;
use App\Services\Database;
use PDO;

class AuthController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            // 1. Check Super Admin (from .env)
            $adminUser = Config::get('ADMIN_USER', 'admin');
            $adminPass = Config::get('ADMIN_PASS', 'admin');

            if ($username === $adminUser && $password === $adminPass) {
                $_SESSION['admin_user'] = [
                    'username' => $adminUser,
                    'role' => 'admin',
                    'agent' => 'all'
                ];
                header('Location: /admin');
                exit;
            }

            // 2. Check Managers (from DB)
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['admin_user'] = [
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'agent' => $user['assigned_agent']
                ];
                header('Location: /admin');
                exit;
            }

            $error = "Credenziali non valide.";
        }

        require __DIR__ . '/../Views/admin/login.php';
    }

    public function logout()
    {
        unset($_SESSION['admin_user']);
        header('Location: /admin/login');
        exit;
    }

    public static function check()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['admin_user'])) {
            header('Location: /admin/login');
            exit;
        }

        return $_SESSION['admin_user'];
    }

    public static function isAdmin()
    {
        $user = self::check();
        return $user['role'] === 'admin';
    }
}
