<?php
// src/controllers/channel.php

if (!isset($username)) {
    die('No channel specified.');
}

$stmt_user = $pdo->prepare("SELECT id, username, created_at, profile_picture FROM users WHERE username = :username");
$stmt_user->execute(['username' => $username]);
$channel_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$channel_user) {
    http_response_code(404);
    require_once '../src/views/404.php';
    exit();
}

$user_id = $channel_user['id'];

$stmt_stats = $pdo->prepare("SELECT COUNT(*) as video_count, SUM(views) as total_views FROM videos WHERE user_id = :user_id");
$stmt_stats->execute(['user_id' => $user_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$stmt_videos = $pdo->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id=u.id WHERE v.user_id = :user_id ORDER BY v.upload_date DESC");
$stmt_videos->execute(['user_id' => $user_id]);
$videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/channel.php';
?>
