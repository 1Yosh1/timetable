<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use BruteForceProtector;

class AuthController extends Controller {
    public function showLoginForm(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $this->redirectToDashboard($_SESSION['role']);
        }
        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();
        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');
        
        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('auth/login', [
            'token' => $token,
            'baseUri' => $baseUri,
            'title' => 'Sign In',
            'bodyClass' => 'login-page-body',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function login(): void {
        $this->validateCsrf();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';

        if ($username === '' || $password === '' || !in_array($role, ['student', 'teacher', 'admin'], true)) {
            $this->redirect('index.php?msg=' . urlencode('Invalid input'));
        }

        require_once __DIR__ . '/../../../BruteForceProtector.php';
        BruteForceProtector::check($username);

        $db = Database::get();
        $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username=? AND role=? LIMIT 1");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                BruteForceProtector::registerSuccess($username);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $this->redirectToDashboard($user['role']);
            }
        }

        BruteForceProtector::registerFailure($username);
        $this->redirect('index.php?msg=' . urlencode('Invalid credentials'));
    }

    public function showAdminLoginForm(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (isset($_SESSION['admin_id'])) {
            $this->redirect('admin_dashboard.php');
        }
        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();
        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('auth/admin_login', [
            'token' => $token,
            'baseUri' => $baseUri,
            'title' => 'Admin Sign In',
            'bodyClass' => 'login-page-body',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function adminLogin(): void {
        $this->validateCsrf();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        require_once __DIR__ . '/../../../BruteForceProtector.php';
        BruteForceProtector::check($username);

        $db = Database::get();
        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ? AND role = 'admin'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($admin_id, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                BruteForceProtector::registerSuccess($username);
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['role'] = 'admin';
                $_SESSION['username'] = $username;
                $this->redirect('admin_dashboard.php');
            }
        }

        BruteForceProtector::registerFailure($username);
        $this->redirect('admin_login.php?msg=' . urlencode('Invalid admin credentials'));
    }

    public function showRegisterForm(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        require_once __DIR__ . '/../../csrf.php';
        $token = csrf_token();
        $baseScript = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseUri = rtrim(dirname($baseScript), '/\\');

        $msg = $_GET['msg'] ?? '';
        $ok = isset($_GET['ok']) && $_GET['ok'] == '1';

        View::render('auth/register', [
            'token' => $token,
            'baseUri' => $baseUri,
            'title' => 'Register',
            'bodyClass' => 'login-page-body',
            'msg' => $msg,
            'ok' => $ok
        ]);
    }

    public function register(): void {
        $this->validateCsrf();

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';

        if ($username === '' || $email === '' || $password === '' || $role === '') {
            $this->redirect('register.php?msg=' . urlencode('All fields are required.'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('register.php?msg=' . urlencode('Invalid email address.'));
        }

        if (!in_array($role, ['student', 'teacher'], true)) {
            $this->redirect('register.php?msg=' . urlencode('Invalid role selected.'));
        }

        if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $this->redirect('register.php?msg=' . urlencode('Username must be 3-50 alphanumeric characters or underscores.'));
        }

        if (strlen($password) < 6) {
            $this->redirect('register.php?msg=' . urlencode('Password must be at least 6 characters long.'));
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $db = Database::get();
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);

        if ($stmt->execute()) {
            $this->redirect('index.php?ok=1&msg=' . urlencode('Registration successful. Please login.'));
        } else {
            $this->redirect('register.php?msg=' . urlencode('Error: The username might already be in use.'));
        }
    }

    public function logout(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $this->redirect('index.php');
    }

    private function redirectToDashboard(string $role): never {
        if ($role === 'admin') {
            $this->redirect('admin_dashboard.php');
        } elseif ($role === 'teacher') {
            $this->redirect('teacher_dashboard.php');
        } else {
            $this->redirect('student_dashboard.php');
        }
    }
}
