<?php
// src/controllers/login.php

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password, is_banned FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['is_banned']) {
            $errors[] = "This account has been suspended.";
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            redirect('/');
        } else {
            $errors[] = "Invalid username or password.";
        }
    } else {
        $errors[] = "Invalid username or password.";
    }
}

require_once '../src/views/login.php';
?>
