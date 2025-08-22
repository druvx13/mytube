<?php
// src/controllers/upload.php

if (!isset($_SESSION['user_id'])) {
    redirect('/login');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_file = $_FILES['video_file'];

    if (empty($title) || $video_file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Title and video file are required.";
    } else {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['mp4', 'webm', 'ogg'];

        if (in_array($file_ext, $allowed_exts)) {
            $thumbnail_filename = null;
            if (!empty($_POST['thumbnail_data'])) {
                list($type, $data) = explode(';', $_POST['thumbnail_data']);
                list(, $data) = explode(',', $data);
                $decoded_data = base64_decode($data);
                if ($decoded_data) {
                    $thumbnail_filename = 'thumb_' . uniqid() . '.jpg';
                    file_put_contents($target_dir . $thumbnail_filename, $decoded_data);
                }
            }

            $new_filename = uniqid('', true) . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($video_file['tmp_name'], $target_file)) {
                $video_id = generate_video_id();
                $user_id = $_SESSION['user_id'];

                $stmt = $pdo->prepare("INSERT INTO videos (user_id, video_id, title, description, filename, thumbnail) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $video_id, $title, $description, $new_filename, $thumbnail_filename])) {
                    redirect('/watch/' . $video_id);
                } else {
                    $errors[] = "Database error while saving video data.";
                }
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        } else {
            $errors[] = "Invalid file type. Only MP4, WebM, and OGG are allowed.";
        }
    }
}

require_once '../src/views/upload.php';
?>
