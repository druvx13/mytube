<?php
// src/controllers/contact.php

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($name) || empty($email) || empty($subject) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "All fields are required and email must be valid.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $subject, $message])) {
            $success_message = "Thank you! Your message has been received.";
        } else {
            $errors[] = "Sorry, there was a problem saving your message. Please try again later.";
        }
    }
}

require_once '../src/views/contact.php';
?>
