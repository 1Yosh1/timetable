<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller {
    protected function validateCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed');
        }
        require_once __DIR__ . '/../csrf.php';
        $token = $_POST['csrf_token'] ?? null;
        if (!verify_csrf($token)) {
            http_response_code(400);
            exit('CSRF validation failed');
        }
    }

    protected function requireRole(array $roles): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $userRole = $_SESSION['role'] ?? null;
        $userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;

        if (!$userId || !in_array($userRole, $roles, true)) {
            $this->redirect('login');
        }
    }

    protected function redirect(string $path): never {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($scriptName), '/\\');
        $url = $basePath . '/' . ltrim($path, '/');
        header("Location: $url");
        exit;
    }

    protected function json(array $data, int $status = 200): never {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
