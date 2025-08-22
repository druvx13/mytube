<?php
// src/controllers/admin/users.php

function log_admin_action($pdo, $action, $target_info = null)
{
    $admin_username = $_SESSION['admin_username'] ?? 'SYSTEM';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_username, action, target_info, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_username, $action, $target_info, $ip_address]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'];
    switch ($action) {
        case 'ban_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            log_admin_action($pdo, 'Banned User', "User ID: $user_id");
            $_SESSION['flash_message'] = "User ID $user_id has been banned.";
            break;
        case 'unban_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            log_admin_action($pdo, 'Unbanned User', "User ID: $user_id");
            $_SESSION['flash_message'] = "User ID $user_id has been unbanned.";
            break;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
if ($search) {
    $where_clause = " WHERE username LIKE ?";
    $params[] = "%" . $search . "%";
}

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users" . $where_clause);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT id, username, email, created_at, is_banned FROM users" . $where_clause . " ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$items_per_page, $offset]));
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/admin/users.php';
?>
