<?php
/**
 * MyTube — Main Application
 * Version 2.0
 *
 * Setup:
 *   1. Copy config.example.php → config.php and set your DB credentials.
 *   2. Ensure the `uploads/` directory exists and is web-server writable.
 *   3. Visit the site — DB tables are created automatically on first run.
 *
 * Libraries bundled via CDN (no composer/npm required):
 *   - Tailwind CSS v3.4.17  (utility-first CSS)
 *   - Video.js  v8.23.7     (full-featured HTML5 video player)
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_setup.php';

// --- Database Connection ---
$db = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$db->set_charset('utf8mb4');

if ($db->connect_error) {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    } else {
        http_response_code(503);
        die('Database connection failed. Please check config.php.');
    }
    exit();
}

run_db_setup($db);

// --- Handle AJAX Requests ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    require __DIR__ . '/includes/ajax.php';
}

// =============================================================================
// PHP ACTIONS
// =============================================================================

$errors          = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF verification for all state-changing POST actions
    if (in_array($action, ['login','signup','upload_video','delete_video','update_picture','contact'], true)) {
        verify_csrf();
    }

    // ---- Update Profile Picture ----------------------------------------
    if ($action === 'update_picture' && isset($_SESSION['user_id'])) {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file          = $_FILES['profile_pic'];
            $allowed_mime  = ['image/jpeg','image/png','image/gif','image/webp'];
            $max_size      = 2 * 1024 * 1024;
            $finfo         = new finfo(FILEINFO_MIME_TYPE);
            $real_mime     = $finfo->file($file['tmp_name']);

            if (!in_array($real_mime, $allowed_mime, true) || $file['size'] > $max_size) {
                $errors[] = 'Invalid file. Must be JPG, PNG, GIF, or WebP and under 2 MB.';
            } else {
                $ext_map  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $ext      = $ext_map[$real_mime];
                $new_name = 'user_' . (int)$_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $target   = UPLOADS_DIR . $new_name;

                $sq = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $sq->bind_param("i", $_SESSION['user_id']);
                $sq->execute();
                $old = $sq->get_result()->fetch_assoc();
                $sq->close();

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $su = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $su->bind_param("si", $new_name, $_SESSION['user_id']);
                    if ($su->execute()) {
                        if (!empty($old['profile_picture']) && file_exists(UPLOADS_DIR . $old['profile_picture'])) {
                            unlink(UPLOADS_DIR . $old['profile_picture']);
                        }
                        $_SESSION['flash_message'] = 'Profile picture updated successfully.';
                        redirect('index.php?page=account');
                    } else {
                        $errors[] = 'Failed to update database.';
                        unlink($target);
                    }
                    $su->close();
                } else {
                    $errors[] = 'Failed to move uploaded file.';
                }
            }
        } else {
            $errors[] = 'No file uploaded or an upload error occurred.';
        }
    }

    // ---- Login ------------------------------------------------------------
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT id, password, is_banned FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid username or password.';
        } elseif ($user['is_banned']) {
            $errors[] = 'This account has been suspended.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $username;
            redirect('index.php');
        }
    }

    // ---- Sign Up ----------------------------------------------------------
    if ($action === 'signup') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hash);
            if ($stmt->execute()) {
                $success_message = 'Account created! Please log in.';
            } else {
                $errors[] = 'Username or email already exists.';
            }
            $stmt->close();
        }
    }

    // ---- Contact Form -----------------------------------------------------
    if ($action === 'contact') {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            if ($stmt->execute()) {
                $success_message = 'Thank you! Your message has been received.';
            } else {
                $errors[] = 'Failed to save your message. Please try again.';
            }
            $stmt->close();
        }
    }

    // ---- Upload Video -----------------------------------------------------
    if ($action === 'upload_video' && isset($_SESSION['user_id'])) {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $vf          = $_FILES['video_file'] ?? null;

        if (empty($title) || !$vf || $vf['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Title and video file are required.';
        } else {
            $allowed_exts  = ['mp4','webm','ogg','mov'];
            $allowed_mimes = ['video/mp4','video/webm','video/ogg','video/quicktime'];
            $file_ext      = strtolower(pathinfo($vf['name'], PATHINFO_EXTENSION));
            $finfo         = new finfo(FILEINFO_MIME_TYPE);
            $real_mime     = $finfo->file($vf['tmp_name']);

            if (!in_array($file_ext, $allowed_exts, true) || !in_array($real_mime, $allowed_mimes, true)) {
                $errors[] = 'Invalid file type. Allowed: MP4, WebM, OGG, MOV.';
            } else {
                $thumb_name = null;
                if (!empty($_POST['thumbnail_data'])) {
                    $parts = explode(';', $_POST['thumbnail_data']);
                    if (count($parts) === 2 && str_starts_with($parts[0], 'data:image/')) {
                        $dp = explode(',', $parts[1]);
                        if (count($dp) === 2) {
                            $decoded = base64_decode($dp[1], true);
                            if ($decoded !== false) {
                                $thumb_name = 'thumb_' . bin2hex(random_bytes(8)) . '.jpg';
                                file_put_contents(UPLOADS_DIR . $thumb_name, $decoded);
                            }
                        }
                    }
                }

                $new_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
                $target       = UPLOADS_DIR . $new_filename;

                if (move_uploaded_file($vf['tmp_name'], $target)) {
                    $video_id = generate_video_id();
                    $uid      = (int) $_SESSION['user_id'];
                    $stmt = $db->prepare(
                        "INSERT INTO videos (user_id, video_id, title, description, filename, thumbnail)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("isssss", $uid, $video_id, $title, $description, $new_filename, $thumb_name);
                    if ($stmt->execute()) {
                        $stmt->close();
                        redirect('index.php?page=watch&v=' . $video_id);
                    } else {
                        $errors[] = 'Database error while saving video.';
                        unlink($target);
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Error uploading the video file.';
                }
            }
        }
    }

    // ---- Delete Video -----------------------------------------------------
    if ($action === 'delete_video' && isset($_SESSION['user_id'])) {
        $vid = (int) ($_POST['video_id'] ?? 0);
        $uid = (int) $_SESSION['user_id'];

        $stmt = $db->prepare("SELECT filename, thumbnail FROM videos WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $vid, $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            foreach ([$row['filename'], $row['thumbnail']] as $f) {
                if ($f && file_exists(UPLOADS_DIR . $f)) unlink(UPLOADS_DIR . $f);
            }
            $sd = $db->prepare("DELETE FROM videos WHERE id = ? AND user_id = ?");
            $sd->bind_param("ii", $vid, $uid);
            if ($sd->execute()) {
                $sd->close();
                redirect('index.php?page=account');
            } else {
                $errors[] = 'Error deleting video from database.';
            }
            $sd->close();
        } else {
            $errors[] = 'You do not have permission to delete this video.';
        }
    }
}

// --- Logout ----------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    redirect('index.php');
}

// --- Route -----------------------------------------------------------------
if (isset($_GET['channel'])) {
    $page             = 'channel';
    $channel_username = $_GET['channel'];
} else {
    $page = $_GET['page'] ?? 'home';
}

csrf_token(); // ensure token exists before rendering HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?></title>

    <!-- Tailwind CSS v3.4.17 Play CDN (no build step required) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: { DEFAULT: '#e00', hover: '#c00' }
                }
            }
        }
    };
    </script>

    <!-- Video.js 8.23.7 -->
    <link  href="https://cdn.jsdelivr.net/npm/video.js@8.23.7/dist/video-js.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/video.js@8.23.7/dist/video.min.js" defer></script>

    <style>
        /* ---------- base ---------- */
        body { background-color:#f1f1f1; font-family:Arial,Helvetica,sans-serif; font-size:.875rem; }
        .container-main { max-width:960px; margin:0 auto; }

        /* ---------- UI primitives ---------- */
        .box          { background:#fff; border:1px solid #ccc; border-radius:4px; }
        .header-box   { background:#e6e6e6; border-bottom:1px solid #ccc; }
        .input-classic{ border:1px solid #999; padding:5px 8px; border-radius:3px;
                        width:100%; box-sizing:border-box; }
        .btn-classic  { border:1px solid #666; background:#ddd; padding:5px 14px;
                        border-radius:3px; cursor:pointer; font-weight:bold; white-space:nowrap;
                        display:inline-block; }
        .btn-classic:hover { background:#ccc; }
        .link-classic { color:#0033cc; text-decoration:none; }
        .link-classic:hover { text-decoration:underline; }

        /* ---------- Video.js tweaks ---------- */
        .video-js { width:100% !important; }
        .vjs-default-skin .vjs-big-play-button {
            left:50%; top:50%; transform:translate(-50%,-50%);
            border-radius:50%; width:60px; height:60px; line-height:60px;
        }

        /* ---------- mobile menu ---------- */
        #mobile-menu { display:none; }
        #mobile-menu.open { display:block; }
    </style>
</head>
<body>

<!-- ======================================================================
     HEADER
====================================================================== -->
<header class="header-box py-2 sticky top-0 z-40">
    <div class="container-main px-4">

        <div class="flex justify-between items-center gap-4">

            <!-- Logo -->
            <a href="index.php"
               class="text-2xl font-bold text-red-600 flex-shrink-0 leading-none">
                My<span class="text-gray-800">Tube</span>
            </a>

            <!-- Desktop search bar -->
            <form action="index.php" method="GET"
                  class="hidden md:flex items-center gap-2 flex-1 max-w-md">
                <input type="hidden" name="page" value="search">
                <input type="text" name="q"
                       value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                       placeholder="Search videos&hellip;"
                       class="input-classic flex-1">
                <button type="submit" class="btn-classic flex-shrink-0">Search</button>
            </form>

            <!-- Desktop nav links -->
            <nav class="hidden md:flex items-center gap-4 flex-shrink-0" aria-label="Main navigation">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="font-bold truncate max-w-[120px]"
                          title="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                        Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="index.php?page=upload"   class="link-classic">Upload</a>
                    <a href="index.php?page=account"  class="link-classic">Account</a>
                    <a href="index.php?action=logout" class="link-classic">Log&nbsp;Out</a>
                <?php else: ?>
                    <a href="index.php?page=signup" class="link-classic">Sign&nbsp;Up</a>
                    <a href="index.php?page=login"  class="link-classic">Log&nbsp;In</a>
                <?php endif; ?>
            </nav>

            <!-- Hamburger button (mobile only) -->
            <button id="hamburger-btn"
                    class="md:hidden p-2 rounded focus:outline-none focus:ring-2 focus:ring-gray-400"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="mobile-menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div><!-- /flex row -->

        <!-- Mobile menu (collapsed by default) -->
        <div id="mobile-menu" role="navigation" aria-label="Mobile navigation">
            <div class="border-t border-gray-300 mt-2 pt-3 pb-2 space-y-3">
                <!-- Mobile search -->
                <form action="index.php" method="GET" class="flex gap-2">
                    <input type="hidden" name="page" value="search">
                    <input type="text" name="q"
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                           placeholder="Search videos&hellip;"
                           class="input-classic flex-1">
                    <button type="submit" class="btn-classic">Go</button>
                </form>
                <!-- Mobile nav links -->
                <div class="flex flex-col gap-2">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="font-bold">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="index.php?page=upload"   class="link-classic">Upload</a>
                        <a href="index.php?page=account"  class="link-classic">My Account</a>
                        <a href="index.php?action=logout" class="link-classic">Log Out</a>
                    <?php else: ?>
                        <a href="index.php?page=signup" class="link-classic">Sign Up</a>
                        <a href="index.php?page=login"  class="link-classic">Log In</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /container -->
</header>

<!-- ======================================================================
     MAIN CONTENT
====================================================================== -->
<main class="container-main mt-4 px-4 pb-8">

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
            <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <?php foreach ($errors as $e): ?>
                <p><?php echo htmlspecialchars($e); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php

    // =========================================================================
    // PAGE ROUTER
    // =========================================================================
    switch ($page):

    // -------------------------------------------------------------------------
    case 'home':
        $cur_pg  = max(1, (int)($_GET['p'] ?? 1));
        $per_pg  = 20;
        $offset  = ($cur_pg - 1) * $per_pg;
        $total   = (int)$db->query("SELECT COUNT(*) FROM videos")->fetch_row()[0];
        $pages   = (int)ceil($total / $per_pg);

        $stmt = $db->prepare(
            "SELECT v.*, u.username FROM videos v
             JOIN users u ON v.user_id = u.id
             ORDER BY upload_date DESC LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("ii", $per_pg, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        ?>
        <h1 class="text-xl font-bold mb-4">Featured Videos</h1>
        <?php if ($result->num_rows > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php while ($v = $result->fetch_assoc()): ?>
                    <?php $wu = 'index.php?page=watch&v=' . htmlspecialchars($v['video_id']); ?>
                    <div class="box p-2 flex flex-col">
                        <a href="<?php echo $wu; ?>" class="block mb-2 aspect-video overflow-hidden">
                            <?php if (!empty($v['thumbnail'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($v['thumbnail']); ?>"
                                     alt="<?php echo htmlspecialchars($v['title']); ?>"
                                     class="w-full h-full object-cover border border-gray-400"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full bg-black flex items-center justify-center
                                            text-white text-xs border border-gray-400">No Thumbnail</div>
                            <?php endif; ?>
                        </a>
                        <div class="flex-grow">
                            <a href="<?php echo $wu; ?>" class="link-classic font-bold leading-tight block line-clamp-2">
                                <?php echo htmlspecialchars($v['title']); ?>
                            </a>
                            <p class="text-xs text-gray-600 mt-1">
                                by <a href="index.php?channel=<?php echo urlencode($v['username']); ?>"
                                      class="link-classic"><?php echo htmlspecialchars($v['username']); ?></a>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo number_format($v['views']); ?> views</p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <?php if ($pages > 1): ?>
                <div class="mt-6 flex flex-wrap justify-between items-center gap-2">
                    <?php if ($cur_pg > 1): ?>
                        <a href="index.php?p=<?php echo $cur_pg - 1; ?>" class="btn-classic">&laquo; Previous</a>
                    <?php else: ?><div></div><?php endif; ?>
                    <span>Page <?php echo $cur_pg; ?> of <?php echo $pages; ?></span>
                    <?php if ($cur_pg < $pages): ?>
                        <a href="index.php?p=<?php echo $cur_pg + 1; ?>" class="btn-classic">Next &raquo;</a>
                    <?php else: ?><div></div><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-gray-600">No videos have been uploaded yet. Be the first to
                <a href="index.php?page=upload" class="link-classic">upload one!</a>
            </p>
        <?php endif; ?>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'search':
        $query = trim($_GET['q'] ?? '');
        ?>
        <h1 class="text-xl font-bold mb-4">
            Search Results for: &ldquo;<?php echo htmlspecialchars($query); ?>&rdquo;
        </h1>
        <?php
        $stmt = $db->prepare(
            "SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id
             WHERE v.title LIKE ? OR v.description LIKE ?
             ORDER BY v.upload_date DESC"
        );
        $term = '%' . $query . '%';
        $stmt->bind_param("ss", $term, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php while ($v = $result->fetch_assoc()): ?>
                    <?php $wu = 'index.php?page=watch&v=' . htmlspecialchars($v['video_id']); ?>
                    <div class="box p-2 flex flex-col">
                        <a href="<?php echo $wu; ?>" class="block mb-2 aspect-video overflow-hidden">
                            <?php if (!empty($v['thumbnail'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($v['thumbnail']); ?>"
                                     alt="<?php echo htmlspecialchars($v['title']); ?>"
                                     class="w-full h-full object-cover border border-gray-400" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full bg-black border border-gray-400
                                            flex items-center justify-center text-white text-xs">No Thumbnail</div>
                            <?php endif; ?>
                        </a>
                        <div class="flex-grow">
                            <a href="<?php echo $wu; ?>" class="link-classic font-bold leading-tight block line-clamp-2">
                                <?php echo htmlspecialchars($v['title']); ?>
                            </a>
                            <p class="text-xs text-gray-600 mt-1">
                                by <a href="index.php?channel=<?php echo urlencode($v['username']); ?>"
                                      class="link-classic"><?php echo htmlspecialchars($v['username']); ?></a>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo number_format($v['views']); ?> views</p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No videos found matching your search.</p>
        <?php endif;
        break;

    // -------------------------------------------------------------------------
    case 'watch':
        if (!isset($_GET['v'])) { echo '<p>No video specified.</p>'; break; }

        $video_public_id = $_GET['v'];
        $stmt = $db->prepare(
            "SELECT v.*, u.username FROM videos v
             JOIN users u ON v.user_id = u.id WHERE v.video_id = ?"
        );
        $stmt->bind_param("s", $video_public_id);
        $stmt->execute();
        $video = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$video) { echo '<p>Video not found.</p>'; break; }

        // Increment view count once per session per video
        if (!isset($_SESSION['viewed_videos'])) $_SESSION['viewed_videos'] = [];
        if (!in_array($video['id'], $_SESSION['viewed_videos'], true)) {
            $uv = $db->prepare("UPDATE videos SET views = views + 1 WHERE id = ?");
            $uv->bind_param("i", $video['id']);
            $uv->execute();
            $uv->close();
            $_SESSION['viewed_videos'][] = $video['id'];
            $video['views']++;
        }

        // Like/dislike counts
        $lk = $db->prepare(
            "SELECT
                SUM(CASE WHEN like_type =  1 THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN like_type = -1 THEN 1 ELSE 0 END) AS dislikes
             FROM likes WHERE video_id = ?"
        );
        $lk->bind_param("i", $video['id']);
        $lk->execute();
        $lkd      = $lk->get_result()->fetch_assoc();
        $lk->close();
        $likes    = (int)($lkd['likes']    ?? 0);
        $dislikes = (int)($lkd['dislikes'] ?? 0);

        // Related videos
        $related = [];
        $words   = array_filter(
            explode(' ', preg_replace('/[^A-Za-z0-9 ]/', '', $video['title'])),
            fn($w) => strlen($w) > 3
        );

        if (!empty($words)) {
            $sp = $wp = [];
            $types  = 'i';
            $vals   = [$video['id']];
            foreach ($words as $w) {
                $sp[] = "(CASE WHEN title LIKE ? THEN 1 ELSE 0 END)";
                $wp[] = "title LIKE ?";
                $types .= 's';
                $vals[] = '%' . $w . '%';
            }
            foreach ($words as $w) { $types .= 's'; $vals[] = '%' . $w . '%'; }

            $rs = $db->prepare(sprintf(
                "SELECT v.*, u.username, (%s) AS relevance
                 FROM videos v JOIN users u ON v.user_id = u.id
                 WHERE v.id != ? AND (%s)
                 ORDER BY relevance DESC, upload_date DESC LIMIT 10",
                implode('+', $sp), implode(' OR ', $wp)
            ));
            if ($rs) {
                $rs->bind_param($types, ...$vals);
                $rs->execute();
                $rr = $rs->get_result();
                while ($row = $rr->fetch_assoc()) $related[] = $row;
                $rs->close();
            }
        }

        if (count($related) < 5) {
            $related = [];
            $fb = $db->prepare(
                "SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id
                 WHERE v.id != ? ORDER BY v.upload_date DESC LIMIT 10"
            );
            $fb->bind_param("i", $video['id']);
            $fb->execute();
            $fbr = $fb->get_result();
            while ($row = $fbr->fetch_assoc()) $related[] = $row;
            $fb->close();
        }

        // Determine MIME type for the <source> tag
        $mime_map = ['mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/mp4'];
        $ext      = strtolower(pathinfo($video['filename'], PATHINFO_EXTENSION));
        $mime     = $mime_map[$ext] ?? 'video/mp4';
        $vsrc     = UPLOADS_URL . htmlspecialchars($video['filename']);

        // Comment pagination
        $cp    = max(1, (int)($_GET['cp'] ?? 1));
        $cper  = 10;
        $coff  = ($cp - 1) * $cper;

        $tc_st = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
        $tc_st->bind_param("i", $video['id']);
        $tc_st->execute();
        $total_comments = (int)$tc_st->get_result()->fetch_row()[0];
        $total_cp       = (int)ceil($total_comments / $cper);
        $tc_st->close();

        $cm_st = $db->prepare(
            "SELECT c.*, u.username, u.profile_picture
             FROM comments c JOIN users u ON c.user_id = u.id
             WHERE c.video_id = ? ORDER BY c.comment_date DESC LIMIT ? OFFSET ?"
        );
        $cm_st->bind_param("iii", $video['id'], $cper, $coff);
        $cm_st->execute();
        $cm_rows = $cm_st->get_result();
        $cm_st->close();
        ?>

        <script>document.title = <?php echo json_encode($video['title'] . ' - ' . APP_NAME); ?>;</script>

        <div class="flex flex-col lg:flex-row gap-4">

            <!-- ── Left column (player + info + comments) ────────────── -->
            <div class="w-full lg:w-2/3 min-w-0">

                <!-- Video.js player -->
                <div class="bg-black rounded overflow-hidden">
                    <video id="mytube-player"
                           class="video-js vjs-default-skin vjs-big-play-centered"
                           controls preload="metadata"
                           data-setup='{"fluid":true,"aspectRatio":"16:9",
                                        "playbackRates":[0.5,1,1.25,1.5,2],
                                        "responsive":true}'>
                        <source src="<?php echo $vsrc; ?>" type="<?php echo $mime; ?>">
                        <p class="vjs-no-js">
                            Please enable JavaScript or upgrade to a
                            <a href="https://videojs.com/html5-video-support/" target="_blank"
                               rel="noopener">browser that supports HTML5 video</a>.
                        </p>
                    </video>
                </div>

                <!-- Video info card -->
                <div class="box mt-4 p-4">
                    <h1 class="text-xl sm:text-2xl font-bold break-words">
                        <?php echo htmlspecialchars($video['title']); ?>
                    </h1>

                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center
                                mt-2 text-gray-600 border-b pb-3 gap-3">
                        <div class="text-xs sm:text-sm">
                            <span class="font-bold"><?php echo number_format($video['views']); ?> views</span>
                            <span class="mx-2 text-gray-400">&bull;</span>
                            <span>Uploaded <?php echo date('M d, Y', strtotime($video['upload_date'])); ?></span>
                        </div>
                        <div class="flex items-center gap-2" id="likeDislikeContainer">
                            <button class="btn-classic like-btn text-xs sm:text-sm"
                                    data-video-id="<?php echo (int)$video['id']; ?>"
                                    data-like-type="1">
                                &#128077; Like (<span id="likeCount"><?php echo $likes; ?></span>)
                            </button>
                            <button class="btn-classic dislike-btn text-xs sm:text-sm"
                                    data-video-id="<?php echo (int)$video['id']; ?>"
                                    data-like-type="-1">
                                &#128078; Dislike (<span id="dislikeCount"><?php echo $dislikes; ?></span>)
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 text-sm">
                        <p class="mb-2">
                            Uploaded by:
                            <a href="index.php?channel=<?php echo urlencode($video['username']); ?>"
                               class="link-classic font-bold"><?php echo htmlspecialchars($video['username']); ?></a>
                        </p>
                        <p class="whitespace-pre-wrap break-words">
                            <?php echo nl2br(htmlspecialchars($video['description'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Comments -->
                <div class="box mt-4 p-4">
                    <h2 class="text-lg font-bold mb-3">Comments</h2>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form id="commentForm" class="mb-4">
                            <input type="hidden" name="action"   value="post_comment">
                            <input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>">
                            <textarea id="commentText" name="comment"
                                      class="w-full input-classic" rows="3"
                                      placeholder="Add a public comment&hellip;"></textarea>
                            <button type="submit" class="btn-classic mt-2">Post Comment</button>
                        </form>
                    <?php else: ?>
                        <p class="mb-4 text-sm">
                            <a href="index.php?page=login" class="link-classic">Log in</a> to post a comment.
                        </p>
                    <?php endif; ?>

                    <div id="commentsContainer">
                        <?php if ($cm_rows->num_rows > 0):
                            while ($c = $cm_rows->fetch_assoc()): ?>
                            <div class="flex gap-3 border-t py-3">
                                <img src="<?php echo get_avatar_url($c['profile_picture']); ?>"
                                     alt="<?php echo htmlspecialchars($c['username']); ?>"
                                     class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0 object-cover mt-1">
                                <div class="min-w-0">
                                    <p>
                                        <a href="index.php?channel=<?php echo urlencode($c['username']); ?>"
                                           class="font-bold link-classic"><?php echo htmlspecialchars($c['username']); ?></a>
                                        <span class="text-xs text-gray-500 ml-1">
                                            <?php echo date('M d, Y, g:i A', strtotime($c['comment_date'])); ?>
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm break-words">
                                        <?php echo nl2br(htmlspecialchars($c['comment'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <p id="noCommentsMsg" class="text-gray-500 text-sm">No comments yet.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_cp > 1): ?>
                        <div class="mt-4 flex flex-wrap justify-between items-center gap-2 border-t pt-2">
                            <?php if ($cp > 1): ?>
                                <a href="index.php?page=watch&v=<?php echo urlencode($video_public_id); ?>&cp=<?php echo $cp-1; ?>"
                                   class="btn-classic text-xs">&laquo; Previous</a>
                            <?php else: ?><div></div><?php endif; ?>
                            <span class="text-xs text-gray-600">Page <?php echo $cp; ?> of <?php echo $total_cp; ?></span>
                            <?php if ($cp < $total_cp): ?>
                                <a href="index.php?page=watch&v=<?php echo urlencode($video_public_id); ?>&cp=<?php echo $cp+1; ?>"
                                   class="btn-classic text-xs">Next &raquo;</a>
                            <?php else: ?><div></div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /left column -->

            <!-- ── Right column (related) ─────────────────────────────── -->
            <aside class="w-full lg:w-1/3">
                <div class="box p-4">
                    <h2 class="font-bold mb-3 text-lg">Up Next</h2>
                    <?php if (!empty($related)): ?>
                        <div class="flex flex-col gap-3">
                            <?php foreach ($related as $rv): ?>
                                <div class="flex gap-2 min-w-0">
                                    <a href="index.php?page=watch&v=<?php echo htmlspecialchars($rv['video_id']); ?>"
                                       class="flex-shrink-0 w-28 aspect-video block overflow-hidden">
                                        <?php if (!empty($rv['thumbnail'])): ?>
                                            <img src="<?php echo UPLOADS_URL . htmlspecialchars($rv['thumbnail']); ?>"
                                                 alt="<?php echo htmlspecialchars($rv['title']); ?>"
                                                 class="w-full h-full object-cover border border-gray-400"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-black border border-gray-400"></div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="flex-1 min-w-0">
                                        <a href="index.php?page=watch&v=<?php echo htmlspecialchars($rv['video_id']); ?>"
                                           class="link-classic font-bold text-xs leading-tight line-clamp-2 block">
                                            <?php echo htmlspecialchars($rv['title']); ?>
                                        </a>
                                        <p class="text-xs text-gray-600 truncate">
                                            <a href="index.php?channel=<?php echo urlencode($rv['username']); ?>"
                                               class="link-classic"><?php echo htmlspecialchars($rv['username']); ?></a>
                                        </p>
                                        <p class="text-xs text-gray-500"><?php echo number_format($rv['views']); ?> views</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No other videos available.</p>
                    <?php endif; ?>
                </div>
            </aside>

        </div><!-- /flex row -->
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'account':
        if (!isset($_SESSION['user_id'])) redirect('index.php?page=login');
        $uid = (int)$_SESSION['user_id'];

        $su = $db->prepare("SELECT username, created_at, profile_picture FROM users WHERE id = ?");
        $su->bind_param("i", $uid); $su->execute();
        $cur_user = $su->get_result()->fetch_assoc(); $su->close();

        $ss = $db->prepare("SELECT COUNT(*) AS cnt, SUM(views) AS tv FROM videos WHERE user_id = ?");
        $ss->bind_param("i", $uid); $ss->execute();
        $stats = $ss->get_result()->fetch_assoc(); $ss->close();
        ?>
        <script>document.title = "My Account - <?php echo APP_NAME; ?>";</script>
        <div class="box p-6 mb-4">
            <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                <img src="<?php echo get_avatar_url($cur_user['profile_picture']); ?>"
                     alt="Your profile picture"
                     class="w-28 h-28 rounded-full border-4 border-gray-200 object-cover flex-shrink-0">
                <div class="flex-grow text-center sm:text-left">
                    <h1 class="text-2xl sm:text-3xl font-bold break-words">
                        <?php echo htmlspecialchars($cur_user['username']); ?>
                    </h1>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 text-gray-600 text-sm">
                        <div><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($cur_user['created_at'])); ?></div>
                        <div><strong>Videos:</strong> <?php echo number_format($stats['cnt']); ?></div>
                        <div class="sm:col-span-2">
                            <strong>Total Views:</strong> <?php echo number_format($stats['tv'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t mt-5 pt-4">
                <h2 class="text-lg font-bold mb-3">Update Profile Picture</h2>
                <form method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="action"     value="update_picture">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="file" name="profile_pic" id="profile_pic"
                           class="input-classic flex-1 min-w-0" required
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <button type="submit" class="btn-classic">Upload</button>
                </form>
                <p class="text-xs text-gray-500 mt-1">Max 2 MB &bull; JPG, PNG, GIF, or WebP</p>
            </div>
        </div>

        <div class="box p-6">
            <h2 class="text-xl font-bold mb-4">My Videos</h2>
            <?php
            $sv = $db->prepare("SELECT id, video_id, title, upload_date FROM videos
                                WHERE user_id = ? ORDER BY upload_date DESC");
            $sv->bind_param("i", $uid); $sv->execute();
            $vr = $sv->get_result(); $sv->close();

            if ($vr->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse min-w-max">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left p-2">Title</th>
                                <th class="text-left p-2 whitespace-nowrap">Uploaded</th>
                                <th class="text-left p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($v = $vr->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2">
                                        <a href="index.php?page=watch&v=<?php echo htmlspecialchars($v['video_id']); ?>"
                                           class="link-classic"><?php echo htmlspecialchars($v['title']); ?></a>
                                    </td>
                                    <td class="p-2 whitespace-nowrap text-gray-600">
                                        <?php echo date('M d, Y', strtotime($v['upload_date'])); ?>
                                    </td>
                                    <td class="p-2">
                                        <form method="POST" class="inline"
                                              onsubmit="return confirm('Delete this video? This cannot be undone.')">
                                            <input type="hidden" name="action"     value="delete_video">
                                            <input type="hidden" name="video_id"   value="<?php echo (int)$v['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                            <button type="submit"
                                                    class="text-red-600 hover:underline cursor-pointer bg-transparent border-0 p-0">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">You haven't uploaded any videos yet.
                    <a href="index.php?page=upload" class="link-classic">Upload one now!</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'channel':
        $su2 = $db->prepare("SELECT id, username, created_at, profile_picture FROM users WHERE username = ?");
        $su2->bind_param("s", $channel_username); $su2->execute();
        $ch = $su2->get_result()->fetch_assoc(); $su2->close();

        if (!$ch) { ?>
            <div class="box p-6">
                <h1 class="text-xl font-bold">Channel Not Found</h1>
                <p class="mt-2">The user &ldquo;<?php echo htmlspecialchars($channel_username); ?>&rdquo; does not exist.</p>
            </div>
        <?php break; }

        $cs = $db->prepare("SELECT COUNT(*) AS cnt, SUM(views) AS tv FROM videos WHERE user_id = ?");
        $cs->bind_param("i", $ch['id']); $cs->execute();
        $ch_stats = $cs->get_result()->fetch_assoc(); $cs->close();

        $cv = $db->prepare(
            "SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id
             WHERE v.user_id = ? ORDER BY v.upload_date DESC"
        );
        $cv->bind_param("i", $ch['id']); $cv->execute();
        $ch_vids = $cv->get_result(); $cv->close();
        ?>
        <script>document.title = <?php echo json_encode($ch['username'] . "'s Channel - " . APP_NAME); ?>;</script>
        <div class="box p-6 mb-4">
            <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                <img src="<?php echo get_avatar_url($ch['profile_picture']); ?>"
                     alt="<?php echo htmlspecialchars($ch['username']); ?>'s avatar"
                     class="w-28 h-28 rounded-full border-4 border-gray-200 object-cover flex-shrink-0">
                <div class="flex-grow text-center sm:text-left">
                    <h1 class="text-2xl sm:text-3xl font-bold break-words">
                        <?php echo htmlspecialchars($ch['username']); ?>&rsquo;s Channel
                    </h1>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 text-gray-600 text-sm">
                        <div><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($ch['created_at'])); ?></div>
                        <div><strong>Videos:</strong> <?php echo number_format($ch_stats['cnt']); ?></div>
                        <div class="sm:col-span-2">
                            <strong>Total Views:</strong> <?php echo number_format($ch_stats['tv'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold mb-4">Videos</h2>
        <?php if ($ch_vids->num_rows > 0): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php while ($v = $ch_vids->fetch_assoc()):
                    $wu = 'index.php?page=watch&v=' . htmlspecialchars($v['video_id']); ?>
                    <div class="box p-2 flex flex-col">
                        <a href="<?php echo $wu; ?>" class="block mb-2 aspect-video overflow-hidden">
                            <?php if (!empty($v['thumbnail'])): ?>
                                <img src="<?php echo UPLOADS_URL . htmlspecialchars($v['thumbnail']); ?>"
                                     alt="<?php echo htmlspecialchars($v['title']); ?>"
                                     class="w-full h-full object-cover border border-gray-400" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full bg-black border border-gray-400"></div>
                            <?php endif; ?>
                        </a>
                        <div class="flex-grow">
                            <a href="<?php echo $wu; ?>" class="link-classic font-bold leading-tight block line-clamp-2">
                                <?php echo htmlspecialchars($v['title']); ?>
                            </a>
                            <p class="text-xs text-gray-500 mt-1"><?php echo number_format($v['views']); ?> views</p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="box p-6"><p>This user hasn't uploaded any videos yet.</p></div>
        <?php endif;
        break;

    // -------------------------------------------------------------------------
    case 'login':
        ?>
        <script>document.title = "Log In - <?php echo APP_NAME; ?>";</script>
        <div class="max-w-md mx-auto box p-6 mt-4">
            <h1 class="text-2xl font-bold mb-5">Log In to Your Account</h1>
            <form method="POST" class="space-y-4" novalidate>
                <input type="hidden" name="action"     value="login">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div>
                    <label for="username" class="block font-bold mb-1">Username</label>
                    <input type="text" id="username" name="username"
                           class="input-classic" required autocomplete="username">
                </div>
                <div>
                    <label for="password" class="block font-bold mb-1">Password</label>
                    <input type="password" id="password" name="password"
                           class="input-classic" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-classic w-full sm:w-auto">Log In</button>
            </form>
            <p class="mt-4 text-xs text-gray-600">
                Don&rsquo;t have an account?
                <a href="index.php?page=signup" class="link-classic">Sign Up</a>
            </p>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'signup':
        ?>
        <script>document.title = "Sign Up - <?php echo APP_NAME; ?>";</script>
        <div class="max-w-md mx-auto box p-6 mt-4">
            <h1 class="text-2xl font-bold mb-5">Create an Account</h1>
            <form method="POST" class="space-y-4" novalidate>
                <input type="hidden" name="action"     value="signup">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div>
                    <label for="username" class="block font-bold mb-1">Username</label>
                    <input type="text" id="username" name="username"
                           class="input-classic" required autocomplete="username">
                </div>
                <div>
                    <label for="email" class="block font-bold mb-1">Email</label>
                    <input type="email" id="email" name="email"
                           class="input-classic" required autocomplete="email">
                </div>
                <div>
                    <label for="password" class="block font-bold mb-1">Password</label>
                    <input type="password" id="password" name="password"
                           class="input-classic" required minlength="6"
                           autocomplete="new-password">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters.</p>
                </div>
                <button type="submit" class="btn-classic w-full sm:w-auto">Sign Up</button>
            </form>
            <p class="mt-4 text-xs text-gray-600">
                Already have an account?
                <a href="index.php?page=login" class="link-classic">Log In</a>
            </p>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'upload':
        if (!isset($_SESSION['user_id'])) redirect('index.php?page=login');
        ?>
        <script>document.title = "Upload Video - <?php echo APP_NAME; ?>";</script>
        <div class="max-w-lg mx-auto box p-6">
            <h1 class="text-2xl font-bold mb-5">Upload a New Video</h1>
            <form method="POST" enctype="multipart/form-data" class="space-y-5" id="uploadForm">
                <input type="hidden" name="action"     value="upload_video">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div>
                    <label for="title" class="block font-bold mb-1">
                        Title <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="title" name="title" class="input-classic" required>
                </div>

                <div>
                    <label for="description" class="block font-bold mb-1">Description</label>
                    <textarea id="description" name="description"
                              class="input-classic" rows="4"></textarea>
                </div>

                <div>
                    <label for="video_file" class="block font-bold mb-1">
                        Video File <span class="text-red-500" aria-hidden="true">*</span>
                        <span class="text-xs font-normal text-gray-500 ml-1">(MP4, WebM, OGG, MOV)</span>
                    </label>
                    <input type="file" id="video_file" name="video_file"
                           class="w-full" accept="video/mp4,video/webm,video/ogg,video/quicktime" required>
                </div>

                <!-- Thumbnail picker (revealed after file selection) -->
                <div id="thumbnailChooser" class="hidden">
                    <p class="block font-bold mb-2">Choose Thumbnail</p>
                    <div class="flex justify-center mb-3">
                        <img id="thumbnailPreview" alt="Selected thumbnail frame"
                             class="border-2 border-gray-400 rounded bg-black"
                             style="width:240px;height:135px;object-fit:cover;">
                    </div>
                    <input type="range" id="thumbnailScrubber"
                           class="w-full accent-red-600" min="0" max="100" step="0.1" value="1">
                    <p class="text-center text-xs text-gray-500 mt-1">
                        Drag the slider to choose a thumbnail frame.
                    </p>
                    <video id="videoPreview" class="hidden" muted playsinline></video>
                    <canvas id="canvas" class="hidden"></canvas>
                    <input type="hidden" name="thumbnail_data" id="thumbnailData">
                </div>

                <!-- Upload progress bar -->
                <div id="uploadProgress" class="hidden">
                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                        <div id="uploadProgressBar"
                             class="bg-red-600 h-2.5 rounded-full transition-all duration-300"
                             style="width:0%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="uploadProgressText">Uploading&hellip;</p>
                </div>

                <button type="submit" id="uploadBtn" class="btn-classic">Upload Video</button>
            </form>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'about':
        ?>
        <script>document.title = "About - <?php echo APP_NAME; ?>";</script>
        <div class="box p-6">
            <h1 class="text-2xl font-bold mb-4">About <?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p class="mb-4">
                Welcome to <?php echo htmlspecialchars(APP_NAME); ?>, a project dedicated to recapturing the
                spirit and simplicity of the early days of video sharing online.
            </p>
            <p class="mb-4">
                The goal is to provide a platform where the focus is on the content and the community,
                free from complex algorithms and cluttered interfaces.
            </p>
            <p>This project was developed by <strong>DK</strong>.</p>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'copyright':
        ?>
        <script>document.title = "Copyright - <?php echo APP_NAME; ?>";</script>
        <div class="box p-6">
            <h1 class="text-2xl font-bold mb-4">Copyright Information</h1>
            <h2 class="text-lg font-bold mb-2">Our Policy</h2>
            <p class="mb-4">
                <?php echo htmlspecialchars(APP_NAME); ?> respects the intellectual property rights of
                others. Please only upload content that you have created yourself or are authorised to use.
            </p>
            <h2 class="text-lg font-bold mb-2">Reporting Infringement</h2>
            <p>
                If you believe your work has been used without permission, please contact us via the
                <a href="index.php?page=contact" class="link-classic">Contact Us</a> page.
            </p>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'contact':
        ?>
        <script>document.title = "Contact Us - <?php echo APP_NAME; ?>";</script>
        <div class="max-w-lg mx-auto box p-6">
            <h1 class="text-2xl font-bold mb-4">Contact Us</h1>
            <p class="mb-4 text-gray-600 text-sm">Have a question or concern? Fill out the form below.</p>
            <form method="POST" class="space-y-4" novalidate>
                <input type="hidden" name="action"     value="contact">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div>
                    <label for="name" class="block font-bold mb-1">Your Name</label>
                    <input type="text"  id="name"    name="name"    class="input-classic" required>
                </div>
                <div>
                    <label for="email" class="block font-bold mb-1">Your Email</label>
                    <input type="email" id="email"   name="email"   class="input-classic" required>
                </div>
                <div>
                    <label for="subject" class="block font-bold mb-1">Subject</label>
                    <input type="text"  id="subject" name="subject" class="input-classic" required>
                </div>
                <div>
                    <label for="message" class="block font-bold mb-1">Message</label>
                    <textarea          id="message"  name="message" class="input-classic" rows="6" required></textarea>
                </div>
                <button type="submit" class="btn-classic">Send Message</button>
            </form>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    default:
        http_response_code(404);
        ?>
        <div class="box p-6">
            <h1 class="text-xl font-bold">Page Not Found</h1>
            <p class="mt-2 text-gray-600">The requested page does not exist.
                <a href="index.php" class="link-classic">Go home</a>
            </p>
        </div>
        <?php
    endswitch;
    ?>

</main>

<!-- ======================================================================
     FOOTER
====================================================================== -->
<footer class="container-main mt-8 border-t pt-4 pb-6 text-center text-gray-500 text-xs px-4">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>.
       A project by DK &bull; v<?php echo APP_VERSION; ?></p>
    <p class="mt-2 space-x-2">
        <a href="index.php?page=about"     class="link-classic">About</a>
        <span aria-hidden="true">&bull;</span>
        <a href="index.php?page=copyright" class="link-classic">Copyright</a>
        <span aria-hidden="true">&bull;</span>
        <a href="index.php?page=contact"   class="link-classic">Contact Us</a>
    </p>
</footer>

<!-- ======================================================================
     JAVASCRIPT
====================================================================== -->
<script>
/* -----------------------------------------------------------------------
   Hamburger mobile menu toggle
----------------------------------------------------------------------- */
(function () {
    var btn  = document.getElementById('hamburger-btn');
    var menu = document.getElementById('mobile-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function () {
        var open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}());

/* -----------------------------------------------------------------------
   Video.js initialisation (watch page)
----------------------------------------------------------------------- */
(function () {
    var el = document.getElementById('mytube-player');
    if (!el || typeof videojs === 'undefined') return;

    var player = videojs(el, {
        fluid          : true,
        aspectRatio    : '16:9',
        playbackRates  : [0.5, 1, 1.25, 1.5, 2],
        responsive     : true,
        html5: {
            vhs: { overrideNative: true }
        }
    });

    // Keyboard shortcut: Space toggles play/pause on the page (not just when player is focused)
    document.addEventListener('keydown', function (e) {
        if (e.code === 'Space' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'INPUT') {
            e.preventDefault();
            player.paused() ? player.play() : player.pause();
        }
    });
}());

/* -----------------------------------------------------------------------
   AJAX — post comment
----------------------------------------------------------------------- */
(function () {
    var form = document.getElementById('commentForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        if (!fd.get('comment').trim()) { alert('Comment cannot be empty.'); return; }

        fetch('index.php', {
            method : 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body   : fd
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var container = document.getElementById('commentsContainer');
                var noMsg     = document.getElementById('noCommentsMsg');
                if (noMsg) noMsg.remove();

                var esc = function (s) {
                    return String(s)
                        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                };
                var html =
                    '<div class="flex gap-3 border-t py-3">' +
                    '  <img src="' + esc(data.comment.avatar_url) + '" alt="' + esc(data.comment.username) + '"' +
                    '       class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0 object-cover mt-1">' +
                    '  <div class="min-w-0">' +
                    '    <p><a href="index.php?channel=' + encodeURIComponent(data.comment.username) +
                    '" class="font-bold link-classic">' + esc(data.comment.username) + '</a>' +
                    ' <span class="text-xs text-gray-500 ml-1">' + esc(data.comment.comment_date) + '</span></p>' +
                    '    <p class="mt-1 text-sm break-words">' + data.comment.comment_text + '</p>' +
                    '  </div></div>';

                container.insertAdjacentHTML('afterbegin', html);
                document.getElementById('commentText').value = '';
            } else {
                alert(data.message || 'An error occurred.');
            }
        })
        .catch(function (err) { console.error('Comment error:', err); });
    });
}());

/* -----------------------------------------------------------------------
   AJAX — like / dislike
----------------------------------------------------------------------- */
(function () {
    var container = document.getElementById('likeDislikeContainer');
    if (!container) return;

    container.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-like-type]');
        if (!btn) return;

        var fd = new FormData();
        fd.append('action',    'like_video');
        fd.append('video_id',  btn.dataset.videoId);
        fd.append('like_type', btn.dataset.likeType);

        fetch('index.php', {
            method : 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body   : fd
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('likeCount').textContent    = data.likes;
                document.getElementById('dislikeCount').textContent = data.dislikes;
            } else {
                alert(data.message || 'You must be logged in to vote.');
            }
        })
        .catch(function (err) { console.error('Like error:', err); });
    });
}());

/* -----------------------------------------------------------------------
   Upload page — thumbnail capture + progress bar
----------------------------------------------------------------------- */
(function () {
    var fileInput = document.getElementById('video_file');
    if (!fileInput) return;

    var chooser      = document.getElementById('thumbnailChooser');
    var videoPreview = document.getElementById('videoPreview');
    var canvas       = document.getElementById('canvas');
    var thumbImg     = document.getElementById('thumbnailPreview');
    var scrubber     = document.getElementById('thumbnailScrubber');
    var dataInput    = document.getElementById('thumbnailData');
    var uploadForm   = document.getElementById('uploadForm');
    var uploadBtn    = document.getElementById('uploadBtn');
    var progressWrap = document.getElementById('uploadProgress');
    var progressBar  = document.getElementById('uploadProgressBar');
    var progressText = document.getElementById('uploadProgressText');
    var ctx          = canvas ? canvas.getContext('2d') : null;

    // Capture a specific frame from videoPreview
    function captureFrame(time) {
        if (videoPreview && !isNaN(time)) videoPreview.currentTime = time;
    }

    // When a file is chosen, show the thumbnail picker
    fileInput.addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        videoPreview.src = URL.createObjectURL(file);
        chooser.classList.remove('hidden');
    });

    if (videoPreview) {
        videoPreview.addEventListener('loadedmetadata', function () {
            canvas.width  = videoPreview.videoWidth  || 640;
            canvas.height = videoPreview.videoHeight || 360;
            scrubber.max  = videoPreview.duration    || 100;
            captureFrame(Math.min(1, videoPreview.duration));
        });

        videoPreview.addEventListener('seeked', function () {
            if (!ctx) return;
            ctx.drawImage(videoPreview, 0, 0, canvas.width, canvas.height);
            var url = canvas.toDataURL('image/jpeg', 0.85);
            thumbImg.src      = url;
            dataInput.value   = url;
        });
    }

    if (scrubber) {
        scrubber.addEventListener('input', function () {
            captureFrame(parseFloat(this.value));
        });
    }

    // XHR upload with progress reporting
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(uploadForm);
            var xhr      = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true);

            xhr.upload.addEventListener('progress', function (ev) {
                if (!ev.lengthComputable) return;
                var pct = Math.round((ev.loaded / ev.total) * 100);
                progressWrap.classList.remove('hidden');
                progressBar.style.width = pct + '%';
                progressText.textContent = pct < 100 ? 'Uploading\u2026 ' + pct + '%' : 'Processing\u2026';
            });

            xhr.addEventListener('load', function () {
                // Server redirects on success; follow the final URL
                if (xhr.responseURL && xhr.responseURL !== window.location.href) {
                    window.location.href = xhr.responseURL;
                } else {
                    // Show server response (might contain error messages)
                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                }
            });

            xhr.addEventListener('error', function () {
                alert('Upload failed due to a network error. Please try again.');
                uploadBtn.disabled = false;
                progressWrap.classList.add('hidden');
            });

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading\u2026';
            xhr.send(formData);
        });
    }
}());
</script>

</body>
</html>
<?php $db->close(); ?>
