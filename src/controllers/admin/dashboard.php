<?php
// src/controllers/admin/dashboard.php

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
$total_views = $pdo->query("SELECT SUM(views) FROM videos")->fetchColumn();
$total_comments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$unread_messages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

require_once '../src/views/admin/dashboard.php';
?>
