<?php
// src/controllers/admin/comments.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $comment_id = intval($_POST['comment_id']);
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    log_admin_action($pdo, 'Deleted Comment', "Comment ID: $comment_id");
    $_SESSION['flash_message'] = "Comment ID $comment_id has been deleted.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
if ($search) {
    $where_clause = " WHERE c.comment LIKE ?";
    $params[] = "%" . $search . "%";
}

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM comments c" . $where_clause);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT c.id, c.comment, u.username, v.title as video_title, c.comment_date FROM comments c JOIN users u ON c.user_id=u.id JOIN videos v ON c.video_id=v.id" . $where_clause . " ORDER BY c.comment_date DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$items_per_page, $offset]));
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/admin/comments.php';
?>
