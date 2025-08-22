<?php
/*
================================================================================
| README: Monolithic 2006-Style YouTube Clone                                  |
================================================================================
|                                                                              |
| Author: DK [DRUVX13 - GITHUB]                                          |
| Version: 1.0                                |
|                                                                              |
| --- SETUP INSTRUCTIONS ---                                                   |
| 1. Place this single `index.php` file in a directory on your web server.     |
| 2. Create a sub-directory named `uploads` in the same directory.             |
| 3. Ensure the web server has write permissions for the `uploads` directory.  |
| 4. The script automatically creates/updates the required tables on first run.|
|                                                                              |
================================================================================
*/

<?php
// public/index.php

session_start();

require_once '../src/database.php';
require_once '../src/helpers.php';

$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/';

// Remove query string from the URI
if (false !== $pos = strpos($request_uri, '?')) {
    $request_uri = substr($request_uri, 0, $pos);
}

// Remove base path from the URI
$route = str_replace($base_path, '', $request_uri);
$route = trim($route, '/');

// Handle basic routing
if ($route === '') {
    require '../src/controllers/home.php';
} elseif (preg_match('/watch\/(\w+)/', $route, $matches)) {
    $video_id = $matches[1];
    require '../src/controllers/watch.php';
} elseif ($route === 'login') {
    require '../src/controllers/login.php';
} elseif ($route === 'signup') {
    require '../src/controllers/signup.php';
} elseif ($route === 'logout') {
    require '../src/controllers/logout.php';
} elseif ($route === 'upload') {
    require '../src/controllers/upload.php';
} elseif ($route === 'account') {
    require '../src/controllers/account.php';
} elseif (preg_match('/channel\/(\w+)/', $route, $matches)) {
    $username = $matches[1];
    require '../src/controllers/channel.php';
} elseif ($route === 'about') {
    require '../src/controllers/about.php';
} elseif ($route === 'copyright') {
    require '../src/controllers/copyright.php';
} elseif ($route === 'contact') {
    require '../src/controllers/contact.php';
} else {
    http_response_code(404);
    require '../src/views/404.php';
}

// --- Helper Functions ---
function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function generate_video_id($length = 11)
{
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
}

function get_avatar_url($filename)
{
    if ($filename && file_exists('uploads/' . $filename)) {
        return 'uploads/' . htmlspecialchars($filename);
    } else {
        // Return a default SVG icon encoded in Base64 to keep it self-contained
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#cccccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}


// =============================================================================
// SECTION 0: AJAX REQUEST HANDLER
// =============================================================================
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'You must be logged in to perform this action.';
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    switch ($action) {
        case 'post_comment':
            $comment_text = trim($_POST['comment'] ?? '');
            $video_internal_id = $_POST['video_id'] ?? 0;
            if (!empty($comment_text) && $video_internal_id > 0) {
                $stmt = $db->prepare("INSERT INTO comments (video_id, user_id, comment) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $video_internal_id, $user_id, $comment_text);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $stmt_user = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt_user->bind_param("i", $user_id);
                    $stmt_user->execute();
                    $user_result = $stmt_user->get_result()->fetch_assoc();
                    $response['comment'] = [
                        'username' => htmlspecialchars($_SESSION['username']),
                        'comment_text' => nl2br(htmlspecialchars($comment_text)),
                        'comment_date' => date('M d, Y, g:i A'),
                        'avatar_url' => get_avatar_url($user_result['profile_picture'])
                    ];
                } else {
                    $response['message'] = 'Failed to save comment.';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Comment cannot be empty.';
            }
            break;

        case 'like_video':
            $video_internal_id = $_POST['video_id'] ?? 0;
            $like_type = $_POST['like_type'] ?? 0;
            if ($video_internal_id > 0 && ($like_type == 1 || $like_type == -1)) {
                $stmt_check = $db->prepare("SELECT id, like_type FROM likes WHERE video_id = ? AND user_id = ?");
                $stmt_check->bind_param("ii", $video_internal_id, $user_id);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                if ($vote = $result->fetch_assoc()) {
                    if ($vote['like_type'] == $like_type) {
                        $stmt_delete = $db->prepare("DELETE FROM likes WHERE id = ?");
                        $stmt_delete->bind_param("i", $vote['id']);
                        $stmt_delete->execute();
                    } else {
                        $stmt_update = $db->prepare("UPDATE likes SET like_type = ? WHERE id = ?");
                        $stmt_update->bind_param("ii", $like_type, $vote['id']);
                        $stmt_update->execute();
                    }
                } else {
                    $stmt_insert = $db->prepare("INSERT INTO likes (video_id, user_id, like_type) VALUES (?, ?, ?)");
                    $stmt_insert->bind_param("iii", $video_internal_id, $user_id, $like_type);
                    $stmt_insert->execute();
                }

                $stmt_counts = $db->prepare("SELECT SUM(CASE WHEN like_type = 1 THEN 1 ELSE 0 END) as likes, SUM(CASE WHEN like_type = -1 THEN 1 ELSE 0 END) as dislikes FROM likes WHERE video_id = ?");
                $stmt_counts->bind_param("i", $video_internal_id);
                $stmt_counts->execute();
                $counts_result = $stmt_counts->get_result()->fetch_assoc();

                $response['success'] = true;
                $response['likes'] = $counts_result['likes'] ?? 0;
                $response['dislikes'] = $counts_result['dislikes'] ?? 0;
            } else {
                $response['message'] = 'Invalid video or like type.';
            }
            break;

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }

    echo json_encode($response);
    $db->close();
    exit();
}


// =============================================================================
// SECTION 1: DATABASE SETUP & MIGRATION
// =============================================================================

$db->query("CREATE TABLE IF NOT EXISTS users (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(100) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, is_admin TINYINT(1) NOT NULL DEFAULT 0, is_banned TINYINT(1) NOT NULL DEFAULT 0, profile_picture VARCHAR(255) NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$db->query("CREATE TABLE IF NOT EXISTS videos (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(11) UNSIGNED NOT NULL, video_id VARCHAR(16) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, description TEXT, filename VARCHAR(255) NOT NULL, thumbnail VARCHAR(255) NULL, views INT(11) UNSIGNED NOT NULL DEFAULT 0, upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
$db->query("CREATE TABLE IF NOT EXISTS comments (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT(11) UNSIGNED NOT NULL, user_id INT(11) UNSIGNED NOT NULL, comment TEXT NOT NULL, comment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
$db->query("CREATE TABLE IF NOT EXISTS likes (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, video_id INT(11) UNSIGNED NOT NULL, user_id INT(11) UNSIGNED NOT NULL, like_type INT(1) NOT NULL, UNIQUE KEY (video_id, user_id), FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
$db->query("CREATE TABLE IF NOT EXISTS contact_messages (id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL, message TEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$is_admin_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_admin'");
if ($is_admin_check->num_rows == 0) {
    $db->query("ALTER TABLE `users` ADD `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`");
}
$is_banned_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_banned'");
if ($is_banned_check->num_rows == 0) {
    $db->query("ALTER TABLE `users` ADD `is_banned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_admin`");
}
$views_column_check = $db->query("SHOW COLUMNS FROM `videos` LIKE 'views'");
if ($views_column_check->num_rows == 0) {
    $db->query("ALTER TABLE `videos` ADD `views` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `thumbnail`");
}
$pfp_column_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'profile_picture'");
if ($pfp_column_check->num_rows == 0) {
    $db->query("ALTER TABLE `users` ADD `profile_picture` VARCHAR(255) NULL DEFAULT NULL AFTER `is_banned`");
}

// =============================================================================
// SECTION 2: PHP LOGIC & ACTIONS
// =============================================================================

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_picture' && isset($_SESSION['user_id'])) {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2 MB
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $target_dir = "uploads/";
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'user_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;
                $stmt_old_pic = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt_old_pic->bind_param("i", $_SESSION['user_id']);
                $stmt_old_pic->execute();
                $old_pic_result = $stmt_old_pic->get_result()->fetch_assoc();
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $stmt_update = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_filename, $_SESSION['user_id']);
                    if ($stmt_update->execute()) {
                        if ($old_pic_result && !empty($old_pic_result['profile_picture']) && file_exists($target_dir . $old_pic_result['profile_picture'])) {
                            unlink($target_dir . $old_pic_result['profile_picture']);
                        }
                        $_SESSION['flash_message'] = "Profile picture updated successfully.";
                        redirect('index.php?page=account');
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
    }

    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $stmt = $db->prepare("SELECT id, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            if ($user['is_banned']) {
                $errors[] = "This account has been suspended.";
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                redirect('index.php');
            } else {
                $errors[] = "Invalid username or password.";
            }
        } else {
            $errors[] = "Invalid username or password.";
        }
        $stmt->close();
    }
    if ($action === 'contact') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        if (empty($name) || empty($email) || empty($subject) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "All fields are required and email must be valid.";
        } else {
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            if ($stmt->execute()) {
                $success_message = "Thank you! Your message has been received.";
            } else {
                $errors[] = "Sorry, there was a problem saving your message. Please try again later.";
            }
            $stmt->close();
        }
    }
    if ($action === 'signup') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        if (empty($username) || empty($email) || empty($password)) {
            $errors[] = "All fields are required.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            if ($stmt->execute()) {
                $success_message = "Account created successfully! Please log in.";
            } else {
                $errors[] = "Username or email already exists.";
            }
            $stmt->close();
        }
    }
    if ($action === 'upload_video' && isset($_SESSION['user_id'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $video_file = $_FILES['video_file'];
        if (empty($title) || $video_file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Title and video file are required.";
        } else {
            $target_dir = "uploads/";
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
                    $stmt = $db->prepare("INSERT INTO videos (user_id, video_id, title, description, filename, thumbnail) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssss", $user_id, $video_id, $title, $description, $new_filename, $thumbnail_filename);
                    if ($stmt->execute()) {
                        redirect('index.php?page=watch&v=' . $video_id);
                    } else {
                        $errors[] = "Database error while saving video data.";
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Sorry, there was an error uploading your file.";
                }
            } else {
                $errors[] = "Invalid file type. Only MP4, WebM, and OGG are allowed.";
            }
        }
    }
    if ($action === 'delete_video' && isset($_SESSION['user_id'])) {
        $video_id_to_delete = $_POST['video_id'];
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT filename, thumbnail FROM videos WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $video_id_to_delete, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($video = $result->fetch_assoc()) {
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
            $stmt_delete = $db->prepare("DELETE FROM videos WHERE id = ? AND user_id = ?");
            $stmt_delete->bind_param("ii", $video_id_to_delete, $user_id);
            if ($stmt_delete->execute()) {
                redirect('index.php?page=account');
            } else {
                $errors[] = "Error deleting video from database.";
            }
        } else {
            $errors[] = "You do not have permission to delete this video.";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    redirect('index.php');
}

if (isset($_GET['channel'])) {
    $page = 'channel';
    $channel_username = $_GET['channel'];
} else {
    $page = $_GET['page'] ?? 'home';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyTube</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #f1f1f1;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container-main {
            max-width: 960px;
            margin: 0 auto;
        }

        .box {
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .header-box {
            background-color: #e6e6e6;
            border-bottom: 1px solid #ccc;
        }

        .input-classic {
            border: 1px solid #999;
            padding: 4px 6px;
            border-radius: 3px;
        }

        .btn-classic {
            border: 1px solid #666;
            background-color: #ddd;
            padding: 4px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-classic:hover {
            background-color: #ccc;
        }

        .link-classic {
            color: #0033cc;
            text-decoration: none;
        }

        .link-classic:hover {
            text-decoration: underline;
        }

        .video-container {
            position: relative;
            background-color: #000;
        }

        .video-container:hover .controls {
            opacity: 1;
        }

        .controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.6);
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            padding: 5px;
        }

        .progress-bar {
            height: 6px;
            background-color: #555;
            cursor: pointer;
        }

        .progress-bar-fill {
            height: 100%;
            background-color: #ff0000;
            width: 0%;
        }

        .control-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0 8px;
            font-size: 16px;
        }

        .volume-slider {
            cursor: pointer;
            -webkit-appearance: none;
            appearance: none;
            width: 80px;
            height: 5px;
            background: #888;
            outline: none;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 12px;
            height: 12px;
            background: #fff;
            cursor: pointer;
            border-radius: 50%;
        }

        .volume-slider::-moz-range-thumb {
            width: 12px;
            height: 12px;
            background: #fff;
            cursor: pointer;
            border-radius: 50%;
        }
    </style>
</head>

<body class="text-sm">
    <div class="header-box py-2">
        <div class="container-main flex justify-between items-center px-4">
            <div class="flex items-center space-x-6">
                <a href="index.php" class="text-2xl font-bold text-red-600">My<span class="text-gray-800">Tube</span></a>
                <form action="index.php" method="GET" class="hidden md:flex items-center">
                    <input type="hidden" name="page" value="search">
                    <input type="text" name="q" placeholder="Search for videos..." class="input-classic w-64">
                    <button type="submit" class="btn-classic ml-2">Search</button>
                </form>
            </div>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <span class="font-bold">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="index.php?page=upload" class="link-classic">Upload</a>
                    <a href="index.php?page=account" class="link-classic">My Account</a>
                    <a href="index.php?action=logout" class="link-classic">Log Out</a>
                <?php else : ?>
                    <a href="index.php?page=signup" class="link-classic">Sign Up</a>
                    <a href="index.php?page=login" class="link-classic">Log In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main class="container-main mt-4 px-4 pb-8">
        <?php
        if (isset($_SESSION['flash_message'])) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
            unset($_SESSION['flash_message']);
        }
        ?>
        <?php if (!empty($errors)) : ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error) : ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success_message) : ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p><?php echo $success_message; ?></p>
            </div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'home':
                $current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
                $items_per_page = 20;
                $offset = ($current_page - 1) * $items_per_page;
                $total_videos = $db->query("SELECT COUNT(*) FROM videos")->fetch_row()[0];
                $total_pages = ceil($total_videos / $items_per_page);
                $stmt = $db->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id ORDER BY upload_date DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $items_per_page, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                echo '<h1 class="text-xl font-bold mb-4">Featured Videos</h1>';
                if ($result->num_rows > 0) {
                    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">';
                    while ($video = $result->fetch_assoc()) {
                        echo '<div class="box p-2 flex flex-col">';
                        echo '  <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="aspect-video block mb-2">';
                        if (!empty($video['thumbnail'])) {
                            echo '<img src="uploads/' . htmlspecialchars($video['thumbnail']) . '" alt="' . htmlspecialchars($video['title']) . '" class="w-full h-full object-cover border border-gray-400">';
                        } else {
                            echo '<div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400">No Thumbnail</div>';
                        }
                        echo '  </a>';
                        echo '  <div class="flex-grow">';
                        echo '    <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="link-classic font-bold">' . htmlspecialchars($video['title']) . '</a>';
                        echo '    <p class="text-xs text-gray-600">by <a href="index.php?channel=' . urlencode($video['username']) . '" class="link-classic">' . htmlspecialchars($video['username']) . '</a></p>';
                        echo '    <p class="text-xs text-gray-500 mt-1">' . number_format($video['views']) . ' views</p>';
                        echo '  </div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '<div class="mt-6 flex justify-between items-center">';
                    if ($current_page > 1) {
                        echo '<a href="index.php?p=' . ($current_page - 1) . '" class="btn-classic">&laquo; Previous</a>';
                    } else {
                        echo '<div></div>';
                    }
                    if ($total_pages > 1) {
                        echo '<span>Page ' . $current_page . ' of ' . $total_pages . '</span>';
                    }
                    if ($current_page < $total_pages) {
                        echo '<a href="index.php?p=' . ($current_page + 1) . '" class="btn-classic">Next &raquo;</a>';
                    } else {
                        echo '<div></div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p>No videos found.</p>';
                }
                break;
            case 'search':
                $query = $_GET['q'] ?? '';
                echo '<h1 class="text-xl font-bold mb-4">Search Results for: "' . htmlspecialchars($query) . '"</h1>';
                $stmt = $db->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.title LIKE ? OR v.description LIKE ? ORDER BY v.upload_date DESC");
                $search_term = "%" . $query . "%";
                $stmt->bind_param("ss", $search_term, $search_term);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">';
                    while ($video = $result->fetch_assoc()) {
                        echo '<div class="box p-2 flex flex-col">';
                        echo '  <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="aspect-video block mb-2">';
                        if (!empty($video['thumbnail'])) {
                            echo '<img src="uploads/' . htmlspecialchars($video['thumbnail']) . '" alt="' . htmlspecialchars($video['title']) . '" class="w-full h-full object-cover border border-gray-400">';
                        } else {
                            echo '<div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400">No Thumbnail</div>';
                        }
                        echo '  </a>';
                        echo '  <div class="flex-grow">';
                        echo '    <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="link-classic font-bold">' . htmlspecialchars($video['title']) . '</a>';
                        echo '    <p class="text-xs text-gray-600">by <a href="index.php?channel=' . urlencode($video['username']) . '" class="link-classic">' . htmlspecialchars($video['username']) . '</a></p>';
                        echo '    <p class="text-xs text-gray-500 mt-1">' . number_format($video['views']) . ' views</p>';
                        echo '  </div>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p>No videos found matching your search.</p>';
                }
                break;
            case 'watch':
                if (isset($_GET['v'])) {
                    $video_public_id = $_GET['v'];
                    $stmt = $db->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.video_id = ?");
                    $stmt->bind_param("s", $video_public_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($video = $result->fetch_assoc()) {
                        if (!isset($_SESSION['viewed_videos'])) {
                            $_SESSION['viewed_videos'] = [];
                        }
                        if (!in_array($video['id'], $_SESSION['viewed_videos'])) {
                            $update_view_stmt = $db->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
                            $update_view_stmt->bind_param("i", $video['id']);
                            $update_view_stmt->execute();
                            $update_view_stmt->close();
                            $_SESSION['viewed_videos'][] = $video['id'];
                            $video['views']++;
                        }
                        $likes_stmt = $db->prepare("SELECT SUM(CASE WHEN like_type = 1 THEN 1 ELSE 0 END) as likes, SUM(CASE WHEN like_type = -1 THEN 1 ELSE 0 END) as dislikes FROM likes WHERE video_id = ?");
                        $likes_stmt->bind_param("i", $video['id']);
                        $likes_stmt->execute();
                        $likes_result = $likes_stmt->get_result()->fetch_assoc();
                        $likes = $likes_result['likes'] ?? 0;
                        $dislikes = $likes_result['dislikes'] ?? 0;
                        $related_videos = [];
                        $title_words = array_filter(explode(' ', preg_replace('/[^A-Za-z0-9 ]/', '', $video['title'])), function ($word) {
                            return strlen($word) > 3;
                        });
                        if (!empty($title_words)) {
                            $relevance_score_sql = [];
                            $where_conditions_sql = [];
                            $params = ['i'];
                            $param_values = [$video['id']];
                            foreach ($title_words as $word) {
                                $relevance_score_sql[] = " (CASE WHEN title LIKE ? THEN 1 ELSE 0 END) ";
                                $where_conditions_sql[] = " title LIKE ? ";
                                $params[0] .= 's';
                                $param_values[] = '%' . $word . '%';
                            }
                            foreach ($title_words as $word) {
                                $params[0] .= 's';
                                $param_values[] = '%' . $word . '%';
                            }
                            $relevance_sql = implode(' + ', $relevance_score_sql);
                            $where_sql = implode(' OR ', $where_conditions_sql);
                            $related_sql = "SELECT v.*, u.username, ($relevance_sql) AS relevance FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id != ? AND ($where_sql) ORDER BY relevance DESC, upload_date DESC LIMIT 10";
                            $related_stmt = $db->prepare($related_sql);
                            if ($related_stmt) {
                                $related_stmt->bind_param(...array_merge($params, $param_values));
                                $related_stmt->execute();
                                $related_result = $related_stmt->get_result();
                                while ($row = $related_result->fetch_assoc()) {
                                    $related_videos[] = $row;
                                }
                            }
                        }
                        if (count($related_videos) < 5) {
                            $related_videos = [];
                            $fallback_stmt = $db->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id != ? ORDER BY v.upload_date DESC LIMIT 10");
                            $fallback_stmt->bind_param("i", $video['id']);
                            $fallback_stmt->execute();
                            $fallback_result = $fallback_stmt->get_result();
                            while ($row = $fallback_result->fetch_assoc()) {
                                $related_videos[] = $row;
                            }
                        }
        ?>
                        <script>
                            document.title = "<?php echo htmlspecialchars($video['title']); ?> - MyTube";
                        </script>
                        <div class="flex flex-col lg:flex-row gap-4">
                            <div class="w-full lg:w-2/3">
                                <div class="video-container aspect-video" id="videoContainer">
                                    <video id="mainVideo" class="w-full h-full" src="uploads/<?php echo htmlspecialchars($video['filename']); ?>"></video>
                                    <div class="controls">
                                        <div class="progress-bar" id="progressBar">
                                            <div class="progress-bar-fill" id="progressBarFill"></div>
                                        </div>
                                        <div class="flex items-center">
                                            <button class="control-btn" id="playPauseBtn">▶</button>
                                            <div class="flex items-center space-x-2"><button class="control-btn" id="volumeBtn">🔊</button><input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.01" value="1"></div>
                                            <div class="text-white text-xs ml-2"><span id="currentTime">00:00</span> / <span id="totalTime">00:00</span></div>
                                            <div class="flex-grow"></div>
                                            <button class="control-btn" id="fullscreenBtn">⛶</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="box mt-4 p-4">
                                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($video['title']); ?></h1>
                                    <div class="flex justify-between items-center mt-2 text-gray-600 border-b pb-2">
                                        <div>
                                            <span class="font-bold"><?php echo number_format($video['views']); ?> views</span>
                                            <span class="mx-2 text-gray-400">•</span>
                                            <span>Uploaded on <?php echo date('M d, Y', strtotime($video['upload_date'])); ?></span>
                                        </div>
                                        <div class="flex items-center space-x-4" id="likeDislikeContainer">
                                            <button class="btn-classic like-btn" data-video-id="<?php echo $video['id']; ?>" data-like-type="1">Like (<span id="likeCount"><?php echo $likes; ?></span>)</button>
                                            <button class="btn-classic dislike-btn" data-video-id="<?php echo $video['id']; ?>" data-like-type="-1">Dislike (<span id="dislikeCount"><?php echo $dislikes; ?></span>)</button>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <p class="mb-2">Uploaded by: <a href="index.php?channel=<?php echo urlencode($video['username']); ?>" class="link-classic font-bold"><?php echo htmlspecialchars($video['username']); ?></a></p>
                                        <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
                                    </div>
                                </div>
                                <div class="box mt-4 p-4">
                                    <h2 class="text-lg font-bold mb-2">Comments</h2>
                                    <?php if (isset($_SESSION['user_id'])) : ?>
                                        <form class="mb-4" id="commentForm">
                                            <input type="hidden" name="action" value="post_comment"><input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                            <textarea name="comment" id="commentText" class="w-full input-classic" rows="3" placeholder="Add a public comment..."></textarea>
                                            <button type="submit" class="btn-classic mt-2">Comment</button>
                                        </form>
                                    <?php else : ?>
                                        <p class="mb-4"><a href="index.php?page=login" class="link-classic">Log in</a> to post a comment.</p>
                                    <?php endif; ?>
                                    <div id="commentsContainer">
                                        <?php
                                        $comments_page = isset($_GET['cp']) ? max(1, intval($_GET['cp'])) : 1;
                                        $comments_per_page = 10;
                                        $comments_offset = ($comments_page - 1) * $comments_per_page;
                                        $total_comments_stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                                        $total_comments_stmt->bind_param("i", $video['id']);
                                        $total_comments_stmt->execute();
                                        $total_comments = $total_comments_stmt->get_result()->fetch_row()[0];
                                        $total_comment_pages = ceil($total_comments / $comments_per_page);
                                        $comments_stmt = $db->prepare("SELECT c.*, u.username, u.profile_picture FROM comments c JOIN users u ON c.user_id = u.id WHERE c.video_id = ? ORDER BY c.comment_date DESC LIMIT ? OFFSET ?");
                                        $comments_stmt->bind_param("iii", $video['id'], $comments_per_page, $comments_offset);
                                        $comments_stmt->execute();
                                        $comments_result = $comments_stmt->get_result();
                                        if ($comments_result->num_rows > 0) {
                                            while ($comment = $comments_result->fetch_assoc()) {
                                                echo '<div class="flex space-x-3 border-t py-3">';
                                                echo '  <img src="' . get_avatar_url($comment['profile_picture']) . '" alt="' . htmlspecialchars($comment['username']) . '" class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0 object-cover">';
                                                echo '  <div>';
                                                echo '      <p><a href="index.php?channel=' . urlencode($comment['username']) . '" class="font-bold link-classic">' . htmlspecialchars($comment['username']) . '</a> <span class="text-xs text-gray-500">(' . date('M d, Y, g:i A', strtotime($comment['comment_date'])) . ')</span></p>';
                                                echo '      <p class="mt-1">' . nl2br(htmlspecialchars($comment['comment'])) . '</p>';
                                                echo '  </div>';
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<p id="noCommentsMsg">No comments yet.</p>';
                                        }
                                        ?>
                                    </div>
                                    <div class="mt-4 flex justify-between items-center border-t pt-2">
                                        <?php if ($comments_page > 1) : ?>
                                            <a href="index.php?page=watch&v=<?php echo $video_public_id; ?>&cp=<?php echo $comments_page - 1; ?>" class="btn-classic text-xs">&laquo; Previous</a>
                                        <?php else : ?>
                                            <div></div>
                                        <?php endif; ?>
                                        <?php if ($total_comment_pages > 1) : ?>
                                            <span class="text-xs text-gray-600">Page <?php echo $comments_page; ?> of <?php echo $total_comment_pages; ?></span>
                                        <?php endif; ?>
                                        <?php if ($comments_page < $total_comment_pages) : ?>
                                            <a href="index.php?page=watch&v=<?php echo $video_public_id; ?>&cp=<?php echo $comments_page + 1; ?>" class="btn-classic text-xs">Next &raquo;</a>
                                        <?php else : ?>
                                            <div></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full lg:w-1/3">
                                <div class="box p-4">
                                    <h2 class="font-bold mb-2 text-lg">Up Next</h2>
                                    <div class="grid grid-cols-2 lg:grid-cols-1 gap-4">
                                        <?php if (!empty($related_videos)) : ?>
                                            <?php foreach ($related_videos as $related_video) : ?>
                                                <div class="box p-2 flex flex-col">
                                                    <a href="index.php?page=watch&v=<?php echo htmlspecialchars($related_video['video_id']); ?>" class="aspect-video block mb-2">
                                                        <?php if (!empty($related_video['thumbnail'])) : ?>
                                                            <img src="uploads/<?php echo htmlspecialchars($related_video['thumbnail']); ?>" alt="<?php echo htmlspecialchars($related_video['title']); ?>" class="w-full h-full object-cover border border-gray-400">
                                                        <?php else : ?>
                                                            <div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400"></div>
                                                        <?php endif; ?>
                                                    </a>
                                                    <div class="flex-grow">
                                                        <a href="index.php?page=watch&v=<?php echo htmlspecialchars($related_video['video_id']); ?>" class="link-classic font-bold text-sm leading-tight"><?php echo htmlspecialchars($related_video['title']); ?></a>
                                                        <p class="text-xs text-gray-600">by <a href="index.php?channel=<?php echo urlencode($related_video['username']); ?>" class="link-classic"><?php echo htmlspecialchars($related_video['username']); ?></a></p>
                                                        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($related_video['views']); ?> views</p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <p class="text-gray-600 col-span-2 lg:col-span-1">No other videos available.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    } else {
                        echo '<p>Video not found.</p>';
                    }
                    $stmt->close();
                } else {
                    echo '<p>No video specified.</p>';
                }
                break;
            case 'account':
                if (!isset($_SESSION['user_id'])) {
                    redirect('index.php?page=login');
                }
                $user_id = $_SESSION['user_id'];
                $stmt_user = $db->prepare("SELECT username, created_at, profile_picture FROM users WHERE id = ?");
                $stmt_user->bind_param("i", $user_id);
                $stmt_user->execute();
                $user_result = $stmt_user->get_result();
                $current_user = $user_result->fetch_assoc();
                $stmt_stats = $db->prepare("SELECT COUNT(*) as video_count, SUM(views) as total_views FROM videos WHERE user_id = ?");
                $stmt_stats->bind_param("i", $user_id);
                $stmt_stats->execute();
                $stats_result = $stmt_stats->get_result();
                $stats = $stats_result->fetch_assoc();
            ?>
                <script>
                    document.title = "My Account - MyTube";
                </script>
                <div class="box p-6 mb-4">
                    <div class="flex flex-col md:flex-row gap-6 items-center">
                        <div class="flex-shrink-0">
                            <img src="<?php echo get_avatar_url($current_user['profile_picture']); ?>" alt="Profile Picture" class="w-32 h-32 rounded-full border-4 border-gray-200 object-cover">
                        </div>
                        <div class="flex-grow">
                            <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($current_user['username']); ?></h1>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 text-gray-600">
                                <div><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($current_user['created_at'])); ?></div>
                                <div><strong>Videos Uploaded:</strong> <?php echo number_format($stats['video_count']); ?></div>
                                <div class="md:col-span-2"><strong>Total Video Views:</strong> <?php echo number_format($stats['total_views'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="border-t mt-4 pt-4">
                        <h2 class="text-lg font-bold mb-2">Update Profile Picture</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_picture">
                            <label for="profile_pic" class="sr-only">Choose file</label>
                            <input type="file" name="profile_pic" id="profile_pic" class="input-classic" required accept="image/jpeg,image/png,image/gif">
                            <button type="submit" class="btn-classic ml-2">Upload</button>
                            <p class="text-xs text-gray-500 mt-1">Max 2MB. JPG, PNG, or GIF.</p>
                        </form>
                    </div>
                </div>
                <div class="box p-6">
                    <h2 class="text-xl font-bold mb-4">My Videos</h2>
                    <?php
                    $stmt_videos = $db->prepare("SELECT id, title, upload_date FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
                    $stmt_videos->bind_param("i", $user_id);
                    $stmt_videos->execute();
                    $videos_result = $stmt_videos->get_result();
                    if ($videos_result->num_rows > 0) {
                        echo '<table class="w-full border-collapse"><thead><tr class="border-b"><th class="text-left p-2">Title</th><th class="text-left p-2">Uploaded</th><th class="text-left p-2">Actions</th></tr></thead><tbody>';
                        while ($video = $videos_result->fetch_assoc()) {
                            echo '<tr class="border-b"><td class="p-2">' . htmlspecialchars($video['title']) . '</td><td class="p-2">' . date('M d, Y', strtotime($video['upload_date'])) . '</td><td class="p-2">';
                            echo '<form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this video?\');"><input type="hidden" name="action" value="delete_video"><input type="hidden" name="video_id" value="' . $video['id'] . '"><button type="submit" class="link-classic text-red-600">Delete</button></form>';
                            echo '</td></tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>You have not uploaded any videos yet. <a href="index.php?page=upload" class="link-classic">Upload one now!</a></p>';
                    }
                    ?>
                </div>
            <?php
                break;
            case 'channel':
                $stmt_user = $db->prepare("SELECT id, username, created_at, profile_picture FROM users WHERE username = ?");
                $stmt_user->bind_param("s", $channel_username);
                $stmt_user->execute();
                $user_result = $stmt_user->get_result();
                if ($channel_user = $user_result->fetch_assoc()) {
                    $user_id = $channel_user['id'];
                    $stmt_stats = $db->prepare("SELECT COUNT(*) as video_count, SUM(views) as total_views FROM videos WHERE user_id = ?");
                    $stmt_stats->bind_param("i", $user_id);
                    $stmt_stats->execute();
                    $stats_result = $stmt_stats->get_result();
                    $stats = $stats_result->fetch_assoc();
                    $stmt_videos = $db->prepare("SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id=u.id WHERE v.user_id = ? ORDER BY v.upload_date DESC");
                    $stmt_videos->bind_param("i", $user_id);
                    $stmt_videos->execute();
                    $videos_result = $stmt_videos->get_result();
            ?>
                    <script>
                        document.title = "<?php echo htmlspecialchars($channel_user['username']); ?>'s Channel - MyTube";
                    </script>
                    <div class="box p-6 mb-4">
                        <div class="flex flex-col md:flex-row gap-6 items-center">
                            <div class="flex-shrink-0">
                                <img src="<?php echo get_avatar_url($channel_user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($channel_user['username']); ?>'s Profile Picture" class="w-32 h-32 rounded-full border-4 border-gray-200 object-cover">
                            </div>
                            <div class="flex-grow">
                                <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($channel_user['username']); ?>'s Channel</h1>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 text-gray-600">
                                    <div><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($channel_user['created_at'])); ?></div>
                                    <div><strong>Videos Uploaded:</strong> <?php echo number_format($stats['video_count']); ?></div>
                                    <div class="md:col-span-2"><strong>Total Video Views:</strong> <?php echo number_format($stats['total_views'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-xl font-bold mb-4">Videos</h2>
                    <?php
                    if ($videos_result->num_rows > 0) {
                        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">';
                        while ($video = $videos_result->fetch_assoc()) {
                            echo '<div class="box p-2 flex flex-col">';
                            echo '  <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="aspect-video block mb-2">';
                            if (!empty($video['thumbnail'])) {
                                echo '<img src="uploads/' . htmlspecialchars($video['thumbnail']) . '" alt="' . htmlspecialchars($video['title']) . '" class="w-full h-full object-cover border border-gray-400">';
                            } else {
                                echo '<div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400">No Thumbnail</div>';
                            }
                            echo '  </a>';
                            echo '  <div class="flex-grow">';
                            echo '    <a href="index.php?page=watch&v=' . htmlspecialchars($video['video_id']) . '" class="link-classic font-bold">' . htmlspecialchars($video['title']) . '</a>';
                            echo '    <p class="text-xs text-gray-500 mt-1">' . number_format($video['views']) . ' views</p>';
                            echo '  </div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="box p-6"><p>This user has not uploaded any videos yet.</p></div>';
                    }
                } else {
                    echo '<div class="box p-6"><h1 class="text-xl font-bold">Channel Not Found</h1><p>The user "' . htmlspecialchars($channel_username) . '" does not exist.</p></div>';
                }
                break;
            case 'login':
            ?>
                <script>
                    document.title = "Log In - MyTube";
                </script>
                <div class="max-w-md mx-auto box p-6">
                    <h1 class="text-2xl font-bold mb-4">Log In to Your Account</h1>
                    <form method="POST"><input type="hidden" name="action" value="login">
                        <div class="mb-4"><label for="username" class="block font-bold mb-1">Username</label><input type="text" id="username" name="username" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="password" class="block font-bold mb-1">Password</label><input type="password" id="password" name="password" class="w-full input-classic" required></div><button type="submit" class="btn-classic">Log In</button>
                    </form>
                </div>
            <?php
                break;
            case 'signup':
            ?>
                <script>
                    document.title = "Sign Up - MyTube";
                </script>
                <div class="max-w-md mx-auto box p-6">
                    <h1 class="text-2xl font-bold mb-4">Create an Account</h1>
                    <form method="POST"><input type="hidden" name="action" value="signup">
                        <div class="mb-4"><label for="username" class="block font-bold mb-1">Username</label><input type="text" id="username" name="username" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="email" class="block font-bold mb-1">Email</label><input type="email" id="email" name="email" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="password" class="block font-bold mb-1">Password</label><input type="password" id="password" name="password" class="w-full input-classic" required></div><button type="submit" class="btn-classic">Sign Up</button>
                    </form>
                </div>
            <?php
                break;
            case 'upload':
                if (!isset($_SESSION['user_id'])) {
                    redirect('index.php?page=login');
                }
            ?>
                <script>
                    document.title = "Upload Video - MyTube";
                </script>
                <div class="max-w-lg mx-auto box p-6">
                    <h1 class="text-2xl font-bold mb-4">Upload a New Video</h1>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_video">
                        <div class="mb-4"><label for="title" class="block font-bold mb-1">Title</label><input type="text" id="title" name="title" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="description" class="block font-bold mb-1">Description</label><textarea id="description" name="description" class="w-full input-classic" rows="5"></textarea></div>
                        <div class="mb-4"><label for="video_file" class="block font-bold mb-1">Video File (MP4, WebM, OGG)</label><input type="file" id="video_file" name="video_file" class="w-full" accept="video/mp4,video/webm,video/ogg" required></div>
                        <div id="thumbnailChooser" class="hidden mb-4">
                            <label class="block font-bold mb-2">Choose Thumbnail</label>
                            <div class="flex justify-center mb-2"><img id="thumbnailPreview" alt="Video frame preview" class="border-2 border-gray-400" style="width:240px; height:135px; object-fit:cover;"></div>
                            <input type="range" id="thumbnailScrubber" class="w-full" min="0" max="100" step="0.1" value="1">
                            <p class="text-center text-xs text-gray-600 mt-1">Move the slider to select a frame.</p>
                            <video id="videoPreview" class="hidden"></video>
                            <canvas id="canvas" class="hidden"></canvas><input type="hidden" name="thumbnail_data" id="thumbnailData">
                        </div>
                        <button type="submit" class="btn-classic">Upload Video</button>
                    </form>
                </div>
            <?php
                break;
            case 'about':
            ?>
                <script>
                    document.title = "About - MyTube";
                </script>
                <div class="box p-6">
                    <h1 class="text-2xl font-bold mb-4">About MyTube</h1>
                    <p class="mb-4">Welcome to MyTube, a project dedicated to recapturing the spirit and simplicity of the internet's golden age of video sharing. This application is a tribute to the YouTube of 2006, built to be simple, functional, and nostalgic.</p>
                    <p class="mb-4">Our goal is to provide a platform where the focus is purely on the content and the community, without the complex algorithms and cluttered interfaces of modern websites.</p>
                    <p>This project was developed by <strong>DK</strong>.</p>
                </div>
            <?php
                break;
            case 'copyright':
            ?>
                <script>
                    document.title = "Copyright - MyTube";
                </script>
                <div class="box p-6">
                    <h1 class="text-2xl font-bold mb-4">Copyright Information</h1>
                    <h2 class="text-lg font-bold mb-2">Our Policy</h2>
                    <p class="mb-4">MyTube respects the intellectual property rights of others and we expect our users to do the same. It is our policy to respond to clear notices of alleged copyright infringement.</p>
                    <p class="mb-4">You should only upload content that you have created yourself or that you are authorized to use.</p>
                    <h2 class="text-lg font-bold mb-2">Reporting Infringement</h2>
                    <p>If you believe your work has been copied in a way that constitutes copyright infringement, please get in touch through our <a href="index.php?page=contact" class="link-classic">Contact Us</a> page.</p>
                </div>
            <?php
                break;
            case 'contact':
            ?>
                <script>
                    document.title = "Contact Us - MyTube";
                </script>
                <div class="max-w-lg mx-auto box p-6">
                    <h1 class="text-2xl font-bold mb-4">Contact Us</h1>
                    <p class="mb-4 text-gray-600">Have a question or a copyright concern? Fill out the form below to get in touch.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="contact">
                        <div class="mb-4"><label for="name" class="block font-bold mb-1">Your Name</label><input type="text" id="name" name="name" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="email" class="block font-bold mb-1">Your Email</label><input type="email" id="email" name="email" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="subject" class="block font-bold mb-1">Subject</label><input type="text" id="subject" name="subject" class="w-full input-classic" required></div>
                        <div class="mb-4"><label for="message" class="block font-bold mb-1">Message</label><textarea id="message" name="message" class="w-full input-classic" rows="6" required></textarea></div>
                        <button type="submit" class="btn-classic">Send Message</button>
                    </form>
                </div>
        <?php
                break;
            default:
                echo '<p>Page not found.</p>';
                break;
        }
        ?>
    </main>
    <footer class="container-main mt-8 border-t pt-4 text-center text-gray-500 text-xs">
        <p>&copy; 2025 MyTube. A project by DK.</p>
        <p class="mt-2">
            <a href="index.php?page=about" class="link-classic">About</a> |
            <a href="index.php?page=copyright" class="link-classic">Copyright</a> |
            <a href="index.php?page=contact" class="link-classic">Contact Us</a>
        </p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const commentForm = document.getElementById('commentForm');
            if (commentForm) {
                commentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    if (!formData.get('comment').trim()) {
                        alert('Comment cannot be empty.');
                        return;
                    }
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    }).then(response => response.json()).then(data => {
                        if (data.success) {
                            const commentsContainer = document.getElementById('commentsContainer');
                            const noCommentsMsg = document.getElementById('noCommentsMsg');
                            if (noCommentsMsg) noCommentsMsg.remove();
                            const newComment = `
                                <div class="flex space-x-3 border-t py-3">
                                    <img src="${data.comment.avatar_url}" alt="${data.comment.username}" class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0 object-cover">
                                    <div>
                                        <p><a href="index.php?channel=${encodeURIComponent(data.comment.username)}" class="font-bold link-classic">${data.comment.username}</a> <span class="text-xs text-gray-500">(${data.comment.comment_date})</span></p>
                                        <p class="mt-1">${data.comment.comment_text}</p>
                                    </div>
                                </div>`;
                            commentsContainer.insertAdjacentHTML('afterbegin', newComment);
                            document.getElementById('commentText').value = '';
                        } else {
                            alert(data.message || 'An error occurred.');
                        }
                    }).catch(error => console.error('Error:', error));
                });
            }
            const likeDislikeContainer = document.getElementById('likeDislikeContainer');
            if (likeDislikeContainer) {
                likeDislikeContainer.addEventListener('click', function(e) {
                    const button = e.target.closest('button');
                    if (button) {
                        const formData = new FormData();
                        formData.append('action', 'like_video');
                        formData.append('video_id', button.dataset.videoId);
                        formData.append('like_type', button.dataset.likeType);
                        fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        }).then(response => response.json()).then(data => {
                            if (data.success) {
                                document.getElementById('likeCount').textContent = data.likes;
                                document.getElementById('dislikeCount').textContent = data.dislikes;
                            } else {
                                alert(data.message || 'An error occurred.');
                            }
                        }).catch(error => console.error('Error:', error));
                    }
                });
            }
        });
        if (document.getElementById('videoContainer')) {
            const videoContainer = document.getElementById('videoContainer'),
                video = document.getElementById('mainVideo'),
                playPauseBtn = document.getElementById('playPauseBtn'),
                progressBar = document.getElementById('progressBar'),
                progressBarFill = document.getElementById('progressBarFill'),
                volumeBtn = document.getElementById('volumeBtn'),
                volumeSlider = document.getElementById('volumeSlider'),
                currentTimeEl = document.getElementById('currentTime'),
                totalTimeEl = document.getElementById('totalTime'),
                fullscreenBtn = document.getElementById('fullscreenBtn');
            const togglePlay = () => {
                if (video.paused) {
                    video.play();
                    playPauseBtn.textContent = '❚❚';
                } else {
                    video.pause();
                    playPauseBtn.textContent = '▶';
                }
            };
            playPauseBtn.addEventListener('click', togglePlay);
            video.addEventListener('click', togglePlay);
            video.addEventListener('timeupdate', () => {
                const progress = (video.currentTime / video.duration) * 100;
                progressBarFill.style.width = `${progress}%`;
                currentTimeEl.textContent = formatTime(video.currentTime);
            });
            video.addEventListener('loadedmetadata', () => {
                totalTimeEl.textContent = formatTime(video.duration);
            });
            progressBar.addEventListener('click', (e) => {
                video.currentTime = (e.offsetX / progressBar.offsetWidth) * video.duration;
            });
            volumeSlider.addEventListener('input', (e) => {
                video.volume = e.target.value;
                video.muted = e.target.value == 0;
            });
            volumeBtn.addEventListener('click', () => {
                video.muted = !video.muted;
                if (video.muted) {
                    volumeSlider.value = 0;
                    volumeBtn.textContent = '🔇';
                } else {
                    volumeSlider.value = video.volume;
                    volumeBtn.textContent = '🔊';
                }
            });
            fullscreenBtn.addEventListener('click', () => {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else {
                    videoContainer.requestFullscreen().catch(err => {
                        alert(`Error: ${err.message}`);
                    });
                }
            });
            const formatTime = (seconds) => {
                const m = Math.floor(seconds / 60),
                    s = Math.floor(seconds % 60);
                return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            };
        }
        if (document.getElementById('thumbnailChooser')) {
            const videoFileInput = document.getElementById('video_file'),
                thumbnailChooser = document.getElementById('thumbnailChooser'),
                videoPreview = document.getElementById('videoPreview'),
                canvas = document.getElementById('canvas'),
                thumbnailPreview = document.getElementById('thumbnailPreview'),
                thumbnailScrubber = document.getElementById('thumbnailScrubber'),
                thumbnailDataInput = document.getElementById('thumbnailData'),
                ctx = canvas.getContext('2d');
            const captureFrame = (time) => {
                if (!isNaN(time) && typeof time !== 'undefined') {
                    videoPreview.currentTime = time;
                }
            };
            videoFileInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (file) {
                    videoPreview.src = URL.createObjectURL(file);
                    thumbnailChooser.classList.remove('hidden');
                }
            });
            videoPreview.addEventListener('loadedmetadata', () => {
                canvas.width = videoPreview.videoWidth;
                canvas.height = videoPreview.videoHeight;
                thumbnailScrubber.max = videoPreview.duration;
                captureFrame(1);
            });
            thumbnailScrubber.addEventListener('input', () => {
                captureFrame(thumbnailScrubber.value);
            });
            videoPreview.addEventListener('seeked', () => {
                ctx.drawImage(videoPreview, 0, 0, canvas.width, canvas.height);
                const dataURL = canvas.toDataURL('image/jpeg', 0.8);
                thumbnailPreview.src = dataURL;
                thumbnailDataInput.value = dataURL;
            });
        }
    </script>
</body>

</html>
<?php
$db->close();
?>
