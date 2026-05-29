<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();

// Authentication Routes
$router->get('/', 'AuthController@showLoginForm');
$router->get('/index.php', 'AuthController@showLoginForm');
$router->post('/login_process.php', 'AuthController@login');
$router->get('/admin_login.php', 'AuthController@showAdminLoginForm');
$router->post('/admin_login_process.php', 'AuthController@adminLogin');
$router->get('/register.php', 'AuthController@showRegisterForm');
$router->post('/register_process.php', 'AuthController@register');
$router->get('/logout.php', 'AuthController@logout');

// Admin Dashboard Routes
$router->get('/admin_dashboard.php', 'AdminController@index');
$router->post('/manage_admin_tasks.php', 'AdminController@handleTasks');

// Teacher Dashboard Routes
$router->get('/teacher_dashboard.php', 'TeacherController@index');
$router->post('/manage_teacher_tasks.php', 'TeacherController@handleTasks');
$router->post('/mark_attendance.php', 'TeacherController@markAttendance');
$router->get('/check_conflict.php', 'TeacherController@checkConflict');

// Student Dashboard Routes
$router->get('/student_dashboard.php', 'StudentController@index');
$router->get('/profile.php', 'StudentController@showProfile');
$router->post('/manage_enrollment.php', 'StudentController@handleEnrollment');
$router->post('/change_password_process.php', 'StudentController@changePassword');

// Dispatch the route
$router->dispatch();