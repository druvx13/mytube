<?php
// src/controllers/signup.php

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
        try {
            $stmt->execute(['username' => $username, 'email' => $email, 'password' => $hashed_password]);
            $success_message = "Account created successfully! Please log in.";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $errors[] = "Username or email already exists.";
            } else {
                $errors[] = "An error occurred during registration.";
            }
        }
    }
}

require_once '../src/views/signup.php';
?>
