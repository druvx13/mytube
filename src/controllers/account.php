<?php
// src/controllers/account.php

if (!isset($_SESSION['user_id'])) {
    redirect('/login');
}

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_picture') {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2 MB
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'user_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;

                $stmt_old_pic = $pdo->prepare("SELECT profile_picture FROM users WHERE id = :id");
                $stmt_old_pic->execute(['id' => $_SESSION['user_id']]);
                $old_pic_result = $stmt_old_pic->fetch(PDO::FETCH_ASSOC);

                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $stmt_update = $pdo->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :id");
                    if ($stmt_update->execute(['profile_picture' => $new_filename, 'id' => $_SESSION['user_id']])) {
                        if ($old_pic_result && !empty($old_pic_result['profile_picture']) && file_exists($target_dir . $old_pic_result['profile_picture'])) {
                            unlink($target_dir . $old_pic_result['profile_picture']);
                        }
                        $_SESSION['flash_message'] = "Profile picture updated successfully.";
                        redirect('/account');
                    } else {
                        $errors[] = "Failed to update database.";
                        unlink($target_file);
                    }
                } else {
                    $errors[] = "Failed to upload file.";
                }
            } else {
                $errors[] = "Invalid file. Must be JPG, PNG, or GIF and under 2MB.";
            }
        } else {
            $errors[] = "No file uploaded or an error occurred.";
        }
    } elseif ($_POST['action'] === 'delete_video') {
        $video_id_to_delete = $_POST['video_id'];
        $stmt = $pdo->prepare("SELECT filename, thumbnail FROM videos WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $video_id_to_delete, 'user_id' => $user_id]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($video) {
            $video_filepath = 'uploads/' . $video['filename'];
            if (file_exists($video_filepath)) {
                unlink($video_filepath);
            }
            if (!empty($video['thumbnail'])) {
                $thumb_filepath = 'uploads/' . $video['thumbnail'];
                if (file_exists($thumb_filepath)) {
                    unlink($thumb_filepath);
                }
            }
            $stmt_delete = $pdo->prepare("DELETE FROM videos WHERE id = :id AND user_id = :user_id");
            if ($stmt_delete->execute(['id' => $video_id_to_delete, 'user_id' => $user_id])) {
                $_SESSION['flash_message'] = "Video deleted successfully.";
                redirect('/account');
            } else {
                $errors[] = "Error deleting video from database.";
            }
        } else {
            $errors[] = "You do not have permission to delete this video.";
        }
    }
}

$stmt_user = $pdo->prepare("SELECT username, created_at, profile_picture FROM users WHERE id = :id");
$stmt_user->execute(['id' => $user_id]);
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

$stmt_stats = $pdo->prepare("SELECT COUNT(*) as video_count, SUM(views) as total_views FROM videos WHERE user_id = :user_id");
$stmt_stats->execute(['user_id' => $user_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$stmt_videos = $pdo->prepare("SELECT id, title, upload_date, video_id FROM videos WHERE user_id = :user_id ORDER BY upload_date DESC");
$stmt_videos->execute(['user_id' => $user_id]);
$videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

require_once '../src/views/account.php';
?>
