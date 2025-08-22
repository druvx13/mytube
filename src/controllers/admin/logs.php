<?php
// src/controllers/admin/logs.php

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$total_items = $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT * FROM admin_logs ORDER BY timestamp DESC LIMIT ? OFFSET ?");
$stmt->execute([$items_per_page, $offset]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/admin/logs.php';
?>
