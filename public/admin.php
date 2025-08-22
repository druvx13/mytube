<?php
// public/admin.php

session_start();

require_once '../src/database.php';
require_once '../src/helpers.php';
require_once '../src/admin-config.php';

// Check if user is admin
if (!isset($_SESSION['is_admin'])) {
    require_once '../src/controllers/admin/login.php';
    exit();
}

// Session security check
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /admin");
    exit();
}
$_SESSION['login_time'] = time(); // Refresh timestamp on activity

$section = $_GET['section'] ?? 'dashboard';

$controller_path = '../src/controllers/admin/' . $section . '.php';

if (file_exists($controller_path)) {
    require_once $controller_path;
} else {
    http_response_code(404);
    require_once '../src/views/admin/404.php';
}
?>
