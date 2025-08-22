<?php
// src/controllers/home.php

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM videos");
$stmt->execute();
$total_videos = $stmt->fetchColumn();

$total_pages = ceil($total_videos / $items_per_page);

$stmt = $pdo->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id ORDER BY upload_date DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/home.php';
?>
