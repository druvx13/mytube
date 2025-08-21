<?php
// Start session to manage admin login state.
session_start();

// =============================================================================
// SECTION 0: CONFIGURATION & SECURITY
// =============================================================================

// --- Admin Credentials (Password is HASHED) ---
define('ADMIN_USER', 'admin');

define('ADMIN_PASS_HASH', '$2y....hash');

// Note: DB credentials should be in a separate config file outside the web root in a production environment.
$db_server = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'mytube';


// =============================================================================
// SECTION 1: AUTHENTICATION & SESSION MANAGEMENT
// =============================================================================

// --- Handle Login ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = ADMIN_USER;
        $_SESSION['login_time'] = time();
        header("Location: admin.php");
        exit();
    } else {
        $login_error = 'Invalid username or password.';
    }
}

// --- Handle Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_start();
    session_unset();
    session_destroy();
    header("Location: admin.php");
    exit();
}

// --- Session Security Check ---
if (isset($_SESSION['is_admin'])) {
    // 30 minute session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: admin.php");
        exit();
    }
    $_SESSION['login_time'] = time(); // Refresh timestamp on activity
}

// If not logged in, show the login page and stop script execution.
if (!isset($_SESSION['is_admin'])) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body {
                background-color: #f1f1f1;
                font-family: Arial, Helvetica, sans-serif;
            }

            .box {
                background-color: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
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
        </style>
    </head>

    <body class="flex items-center justify-center h-screen">
        <div class="w-full max-w-xs">
            <form class="box shadow-md rounded px-8 pt-6 pb-8 mb-4" method="POST">
                <input type="hidden" name="action" value="login">
                <h1 class="text-2xl font-bold mb-4 text-center">MyTube Admin</h1>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                    <input class="input-classic w-full" id="username" name="username" type="text" placeholder="Username" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input class="input-classic w-full" id="password" name="password" type="password" placeholder="******************" required>
                </div>
                <?php if ($login_error) : ?>
                    <p class="text-red-500 text-xs italic mb-4"><?php echo $login_error; ?></p>
                <?php endif; ?>
                <div class="flex items-center justify-between">
                    <button class="btn-classic w-full" type="submit">Sign In</button>
                </div>
            </form>
        </div>
    </body>

    </html>
<?php
    exit(); // Stop here if not logged in.
}

// =============================================================================
// SECTION 2: CORE ADMIN LOGIC (for logged-in admins)
// =============================================================================

// --- Database Connection ---
$db = new mysqli($db_server, $db_username, $db_password, $db_name);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// --- Automatic DB Migrations ---
$is_admin_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_admin'");
if ($is_admin_check->num_rows == 0) {
    $db->query("ALTER TABLE `users` ADD `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`");
}
$is_banned_check = $db->query("SHOW COLUMNS FROM `users` LIKE 'is_banned'");
if ($is_banned_check->num_rows == 0) {
    $db->query("ALTER TABLE `users` ADD `is_banned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_admin`");
}
$db->query("CREATE TABLE IF NOT EXISTS admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_username VARCHAR(255), action VARCHAR(255), target_info TEXT, ip_address VARCHAR(45), timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");


// --- Helper Function for Admin Logging ---
function log_admin_action($db, $action, $target_info = null)
{
    $admin_username = $_SESSION['admin_username'] ?? 'SYSTEM';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $db->prepare("INSERT INTO admin_logs (admin_username, action, target_info, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $admin_username, $action, $target_info, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// --- Handle Admin POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'];
    switch ($action) {
        case 'ban_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $db->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            log_admin_action($db, 'Banned User', "User ID: $user_id");
            $_SESSION['flash_message'] = "User ID $user_id has been banned.";
            break;
        case 'unban_user':
            $user_id = intval($_POST['user_id']);
            $stmt = $db->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            log_admin_action($db, 'Unbanned User', "User ID: $user_id");
            $_SESSION['flash_message'] = "User ID $user_id has been unbanned.";
            break;
        case 'delete_video':
            $video_id = intval($_POST['video_id']);
            $stmt = $db->prepare("SELECT filename, thumbnail FROM videos WHERE id = ?");
            $stmt->bind_param("i", $video_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($video = $result->fetch_assoc()) {
                if (file_exists('uploads/' . $video['filename'])) unlink('uploads/' . $video['filename']);
                if (file_exists('uploads/' . $video['thumbnail'])) unlink('uploads/' . $video['thumbnail']);
            }
            $stmt = $db->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->bind_param("i", $video_id);
            $stmt->execute();
            log_admin_action($db, 'Deleted Video', "Video ID: $video_id");
            $_SESSION['flash_message'] = "Video ID $video_id has been deleted.";
            break;
        case 'delete_comment':
            $comment_id = intval($_POST['comment_id']);
            $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            log_admin_action($db, 'Deleted Comment', "Comment ID: $comment_id");
            $_SESSION['flash_message'] = "Comment ID $comment_id has been deleted.";
            break;
        case 'toggle_message_read':
            $message_id = intval($_POST['message_id']);
            $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?");
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            log_admin_action($db, 'Toggled Message Read Status', "Message ID: $message_id");
            $_SESSION['flash_message'] = "Message ID $message_id read status updated.";
            break;
        case 'delete_message':
            $message_id = intval($_POST['message_id']);
            $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            log_admin_action($db, 'Deleted Message', "Message ID: $message_id");
            $_SESSION['flash_message'] = "Message ID $message_id has been deleted.";
            break;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Generate a new CSRF token for this page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$section = $_GET['section'] ?? 'dashboard';
$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MyTube Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #f9fafb;
            font-family: Arial, sans-serif;
        }

        .box {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }

        .stat-card {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .table-auto th,
        .table-auto td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }

        .table-auto th {
            background-color: #f9fafb;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
        }

        .btn-red {
            background-color: #ef4444;
            color: white;
        }

        .btn-red:hover {
            background-color: #dc2626;
        }

        .btn-green {
            background-color: #22c55e;
            color: white;
        }

        .btn-green:hover {
            background-color: #16a34a;
        }

        .btn-blue {
            background-color: #3b82f6;
            color: white;
        }

        .btn-blue:hover {
            background-color: #2563eb;
        }

        .sidebar-link {
            display: block;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            color: #d1d5db;
        }

        .sidebar-link:hover {
            background-color: #374151;
            color: white;
        }

        .sidebar-link.active {
            background-color: #4f46e5;
            color: white;
        }
    </style>
</head>

<body>
    <div class="flex">
        <aside class="w-64 bg-gray-800 text-white min-h-screen p-4 flex flex-col">
            <h1 class="text-2xl font-bold mb-6">MyTube Admin</h1>
            <nav class="flex-grow">
                <a href="admin.php?section=dashboard" class="sidebar-link <?php if ($section === 'dashboard') echo 'active'; ?>">Dashboard</a>
                <a href="admin.php?section=users" class="sidebar-link <?php if ($section === 'users') echo 'active'; ?>">User Management</a>
                <a href="admin.php?section=videos" class="sidebar-link <?php if ($section === 'videos') echo 'active'; ?>">Video Moderation</a>
                <a href="admin.php?section=comments" class="sidebar-link <?php if ($section === 'comments') echo 'active'; ?>">Comment Moderation</a>
                <a href="admin.php?section=messages" class="sidebar-link <?php if ($section === 'messages') echo 'active'; ?>">Contact Messages</a>
                <a href="admin.php?section=logs" class="sidebar-link <?php if ($section === 'logs') echo 'active'; ?>">Admin Logs</a>
            </nav>
            <a href="admin.php?action=logout" class="sidebar-link">Logout</a>
        </aside>

        <main class="flex-1 p-8">
            <?php
            if (isset($_SESSION['flash_message'])) {
                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message']);
            }

            // --- CORRECTED SYNTAX: switch(...): ---
            switch ($section):

                case 'dashboard':
                    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
                    $total_videos = $db->query("SELECT COUNT(*) FROM videos")->fetch_row()[0];
                    $total_views = $db->query("SELECT SUM(views) FROM videos")->fetch_row()[0];
                    $total_comments = $db->query("SELECT COUNT(*) FROM comments")->fetch_row()[0];
                    $unread_messages = $db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetch_row()[0];
            ?>
                    <h1 class="text-3xl font-bold mb-6">Dashboard</h1>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="stat-card">
                            <h2 class="text-gray-500">Total Users</h2>
                            <p class="text-3xl font-bold"><?php echo number_format($total_users); ?></p>
                        </div>
                        <div class="stat-card">
                            <h2 class="text-gray-500">Total Videos</h2>
                            <p class="text-3xl font-bold"><?php echo number_format($total_videos); ?></p>
                        </div>
                        <div class="stat-card">
                            <h2 class="text-gray-500">Total Views</h2>
                            <p class="text-3xl font-bold"><?php echo number_format($total_views ?? 0); ?></p>
                        </div>
                        <div class="stat-card">
                            <h2 class="text-gray-500">Total Comments</h2>
                            <p class="text-3xl font-bold"><?php echo number_format($total_comments); ?></p>
                        </div>
                        <div class="stat-card">
                            <h2 class="text-gray-500">Unread Messages</h2>
                            <p class="text-3xl font-bold"><?php echo number_format($unread_messages); ?></p>
                        </div>
                    </div>
                <?php break;

                case 'users':
                    $search = $_GET['search'] ?? '';
                    $where_clause = '';
                    if ($search) {
                        $where_clause = " WHERE username LIKE '%" . $db->real_escape_string($search) . "%'";
                    }
                    $total_items = $db->query("SELECT COUNT(*) FROM users" . $where_clause)->fetch_row()[0];
                    $total_pages = ceil($total_items / $items_per_page);
                    $users = $db->query("SELECT id, username, email, created_at, is_banned FROM users" . $where_clause . " ORDER BY created_at DESC LIMIT $items_per_page OFFSET $offset");
            ?>
                    <h1 class="text-3xl font-bold mb-6">User Management</h1>
                    <form class="mb-4"><input type="hidden" name="section" value="users"><input type="text" name="search" placeholder="Search by username..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>"><button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button></form>
                    <div class="box overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        <td><?php echo $user['is_banned'] ? '<span class="text-red-500 font-bold">Banned</span>' : '<span class="text-green-500">Active</span>'; ?></td>
                                        <td>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <?php if ($user['is_banned']) : ?>
                                                    <button type="submit" name="action" value="unban_user" class="btn-sm btn-green">Unban</button>
                                                <?php else : ?>
                                                    <button type="submit" name="action" value="ban_user" class="btn-sm btn-red" onclick="return confirm('Ban this user?')">Ban</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <?php if ($current_page > 1) : ?><a href="?section=users&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a><?php endif; ?>
                        <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($current_page < $total_pages) : ?><a href="?section=users&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a><?php endif; ?>
                    </div>
                <?php break;

                case 'videos':
                    $search = $_GET['search'] ?? '';
                    $where_clause = '';
                    if ($search) {
                        $where_clause = " WHERE v.title LIKE '%" . $db->real_escape_string($search) . "%'";
                    }
                    $total_items = $db->query("SELECT COUNT(*) FROM videos v" . $where_clause)->fetch_row()[0];
                    $total_pages = ceil($total_items / $items_per_page);
                    $videos = $db->query("SELECT v.id, v.thumbnail, v.title, u.username, v.views, v.upload_date FROM videos v JOIN users u ON v.user_id = u.id" . $where_clause . " ORDER BY v.upload_date DESC LIMIT $items_per_page OFFSET $offset");
            ?>
                    <h1 class="text-3xl font-bold mb-6">Video Moderation</h1>
                    <form class="mb-4"><input type="hidden" name="section" value="videos"><input type="text" name="search" placeholder="Search by video title..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>"><button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button></form>
                    <div class="box overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th>Thumbnail</th>
                                    <th>Title</th>
                                    <th>Uploader</th>
                                    <th>Views</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($video = $videos->fetch_assoc()) : ?>
                                    <tr>
                                        <td><img src="uploads/<?php echo htmlspecialchars($video['thumbnail']); ?>" class="w-24 h-auto" onerror="this.style.display='none'"></td>
                                        <td><?php echo htmlspecialchars($video['title']); ?></td>
                                        <td><?php echo htmlspecialchars($video['username']); ?></td>
                                        <td><?php echo number_format($video['views']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($video['upload_date'])); ?></td>
                                        <td>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this video permanently?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_video">
                                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                                <button type="submit" class="btn-sm btn-red">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <?php if ($current_page > 1) : ?><a href="?section=videos&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a><?php endif; ?>
                        <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($current_page < $total_pages) : ?><a href="?section=videos&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a><?php endif; ?>
                    </div>
                <?php break;

                case 'comments':
                    $search = $_GET['search'] ?? '';
                    $where_clause = '';
                    if ($search) {
                        $where_clause = " WHERE c.comment LIKE '%" . $db->real_escape_string($search) . "%'";
                    }
                    $total_items = $db->query("SELECT COUNT(*) FROM comments c" . $where_clause)->fetch_row()[0];
                    $total_pages = ceil($total_items / $items_per_page);
                    $comments = $db->query("SELECT c.id, c.comment, u.username, v.title as video_title, c.comment_date FROM comments c JOIN users u ON c.user_id=u.id JOIN videos v ON c.video_id=v.id" . $where_clause . " ORDER BY c.comment_date DESC LIMIT $items_per_page OFFSET $offset");
            ?>
                    <h1 class="text-3xl font-bold mb-6">Comment Moderation</h1>
                    <form class="mb-4"><input type="hidden" name="section" value="comments"><input type="text" name="search" placeholder="Search in comments..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>"><button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button></form>
                    <div class="box overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th class="w-1/2">Comment</th>
                                    <th>User</th>
                                    <th>On Video</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($comment = $comments->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($comment['comment']); ?></td>
                                        <td><?php echo htmlspecialchars($comment['username']); ?></td>
                                        <td><?php echo htmlspecialchars($comment['video_title']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($comment['comment_date'])); ?></td>
                                        <td>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this comment?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                <button type="submit" class="btn-sm btn-red">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <?php if ($current_page > 1) : ?><a href="?section=comments&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a><?php endif; ?>
                        <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($current_page < $total_pages) : ?><a href="?section=comments&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a><?php endif; ?>
                    </div>
                <?php break;

                case 'messages':
                    $total_items = $db->query("SELECT COUNT(*) FROM contact_messages")->fetch_row()[0];
                    $total_pages = ceil($total_items / $items_per_page);
                    $messages = $db->query("SELECT * FROM contact_messages ORDER BY received_at DESC LIMIT $items_per_page OFFSET $offset");
            ?>
                    <h1 class="text-3xl font-bold mb-6">Contact Form Messages</h1>
                    <div class="box overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>Subject</th>
                                    <th>Received</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($msg = $messages->fetch_assoc()) : ?>
                                    <tr class="<?php if (!$msg['is_read']) echo 'font-bold'; ?>">
                                        <td><?php echo htmlspecialchars($msg['name']) . ' &lt;' . htmlspecialchars($msg['email']) . '&gt;'; ?></td>
                                        <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($msg['received_at'])); ?></td>
                                        <td><?php echo $msg['is_read'] ? 'Read' : 'Unread'; ?></td>
                                        <td>
                                            <button onclick="alert('<?php echo htmlspecialchars(addslashes($msg['message'])); ?>')" class="btn-sm btn-blue">View</button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="toggle_message_read"><input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn-sm <?php echo $msg['is_read'] ? 'btn-green' : 'btn-blue'; ?>"><?php echo $msg['is_read'] ? 'Mark Unread' : 'Mark Read'; ?></button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete_message"><input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn-sm btn-red">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <?php if ($current_page > 1) : ?><a href="?section=messages&p=<?php echo $current_page - 1; ?>" class="p-2 bg-gray-300 rounded">Previous</a><?php endif; ?>
                        <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($current_page < $total_pages) : ?><a href="?section=messages&p=<?php echo $current_page + 1; ?>" class="p-2 bg-gray-300 rounded">Next</a><?php endif; ?>
                    </div>
                <?php break;

                case 'logs':
                    $total_items = $db->query("SELECT COUNT(*) FROM admin_logs")->fetch_row()[0];
                    $total_pages = ceil($total_items / $items_per_page);
                    $logs = $db->query("SELECT * FROM admin_logs ORDER BY timestamp DESC LIMIT $items_per_page OFFSET $offset");
            ?>
                    <h1 class="text-3xl font-bold mb-6">Admin Activity Logs</h1>
                    <div class="box overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $logs->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?php echo $log['timestamp']; ?></td>
                                        <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['target_info']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <?php if ($current_page > 1) : ?><a href="?section=logs&p=<?php echo $current_page - 1; ?>" class="p-2 bg-gray-300 rounded">Previous</a><?php endif; ?>
                        <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($current_page < $total_pages) : ?><a href="?section=logs&p=<?php echo $current_page + 1; ?>" class="p-2 bg-gray-300 rounded">Next</a><?php endif; ?>
                    </div>
            <?php break;

            endswitch;
            ?>
        </main>
    </div>
</body>

</html>
