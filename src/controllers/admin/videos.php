<?php
// src/controllers/admin/videos.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $video_id = intval($_POST['video_id']);
    $stmt = $pdo->prepare("SELECT filename, thumbnail FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($video) {
        if (file_exists('uploads/' . $video['filename'])) unlink('uploads/' . $video['filename']);
        if (file_exists('uploads/' . $video['thumbnail'])) unlink('uploads/' . $video['thumbnail']);
    }

    $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    log_admin_action($pdo, 'Deleted Video', "Video ID: $video_id");
    $_SESSION['flash_message'] = "Video ID $video_id has been deleted.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
if ($search) {
    $where_clause = " WHERE v.title LIKE ?";
    $params[] = "%" . $search . "%";
}

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM videos v" . $where_clause);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT v.id, v.thumbnail, v.title, u.username, v.views, v.upload_date FROM videos v JOIN users u ON v.user_id = u.id" . $where_clause . " ORDER BY v.upload_date DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$items_per_page, $offset]));
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/admin/videos.php';
?>
