<?php
// src/controllers/admin/messages.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'];
    switch ($action) {
        case 'toggle_message_read':
            $message_id = intval($_POST['message_id']);
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?");
            $stmt->execute([$message_id]);
            log_admin_action($pdo, 'Toggled Message Read Status', "Message ID: $message_id");
            $_SESSION['flash_message'] = "Message ID $message_id read status updated.";
            break;
        case 'delete_message':
            $message_id = intval($_POST['message_id']);
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            log_admin_action($pdo, 'Deleted Message', "Message ID: $message_id");
            $_SESSION['flash_message'] = "Message ID $message_id has been deleted.";
            break;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$total_items = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("SELECT * FROM contact_messages ORDER BY received_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$items_per_page, $offset]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/admin/messages.php';
?>
