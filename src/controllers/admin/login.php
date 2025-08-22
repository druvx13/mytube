<?php
// src/controllers/admin/login.php

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = ADMIN_USER;
        $_SESSION['login_time'] = time();
        header("Location: /admin");
        exit();
    } else {
        $login_error = 'Invalid username or password.';
    }
}

require_once '../src/views/admin/login.php';
?>
