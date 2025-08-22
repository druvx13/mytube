<?php
// src/controllers/watch.php

if (!isset($video_id)) {
    die('No video specified.');
}

$stmt = $pdo->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.video_id = :video_id");
$stmt->execute(['video_id' => $video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    http_response_code(404);
    require_once '../src/views/404.php';
    exit();
}

if (!isset($_SESSION['viewed_videos'])) {
    $_SESSION['viewed_videos'] = [];
}
if (!in_array($video['id'], $_SESSION['viewed_videos'])) {
    $update_view_stmt = $pdo->prepare("UPDATE videos SET views = views + 1 WHERE id = :id");
    $update_view_stmt->execute(['id' => $video['id']]);
    $_SESSION['viewed_videos'][] = $video['id'];
    $video['views']++;
}

$likes_stmt = $pdo->prepare("SELECT SUM(CASE WHEN like_type = 1 THEN 1 ELSE 0 END) as likes, SUM(CASE WHEN like_type = -1 THEN 1 ELSE 0 END) as dislikes FROM likes WHERE video_id = :video_id");
$likes_stmt->execute(['video_id' => $video['id']]);
$likes_result = $likes_stmt->fetch(PDO::FETCH_ASSOC);
$likes = $likes_result['likes'] ?? 0;
$dislikes = $likes_result['dislikes'] ?? 0;

// Related videos logic (simplified for now)
$related_stmt = $pdo->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id != :id ORDER BY RAND() LIMIT 10");
$related_stmt->execute(['id' => $video['id']]);
$related_videos = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

$comments_page = isset($_GET['cp']) ? max(1, intval($_GET['cp'])) : 1;
$comments_per_page = 10;
$comments_offset = ($comments_page - 1) * $comments_per_page;

$total_comments_stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE video_id = :video_id");
$total_comments_stmt->execute(['video_id' => $video['id']]);
$total_comments = $total_comments_stmt->fetchColumn();
$total_comment_pages = ceil($total_comments / $comments_per_page);

$comments_stmt = $pdo->prepare("SELECT c.*, u.username, u.profile_picture FROM comments c JOIN users u ON c.user_id = u.id WHERE c.video_id = :video_id ORDER BY c.comment_date DESC LIMIT :limit OFFSET :offset");
$comments_stmt->bindValue(':video_id', $video['id'], PDO::PARAM_INT);
$comments_stmt->bindValue(':limit', $comments_per_page, PDO::PARAM_INT);
$comments_stmt->bindValue(':offset', $comments_offset, PDO::PARAM_INT);
$comments_stmt->execute();
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/watch.php';
?>
