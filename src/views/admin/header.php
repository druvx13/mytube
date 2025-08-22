<?php
// src/views/admin/header.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MyTube Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body>
    <div class="flex">
        <aside class="w-64 bg-gray-800 text-white min-h-screen p-4 flex flex-col">
            <h1 class="text-2xl font-bold mb-6">MyTube Admin</h1>
            <nav class="flex-grow">
                <a href="/admin?section=dashboard" class="sidebar-link <?php if ($section === 'dashboard') echo 'active'; ?>">Dashboard</a>
                <a href="/admin?section=users" class="sidebar-link <?php if ($section === 'users') echo 'active'; ?>">User Management</a>
                <a href="/admin?section=videos" class="sidebar-link <?php if ($section === 'videos') echo 'active'; ?>">Video Moderation</a>
                <a href="/admin?section=comments" class="sidebar-link <?php if ($section === 'comments') echo 'active'; ?>">Comment Moderation</a>
                <a href="/admin?section=messages" class="sidebar-link <?php if ($section === 'messages') echo 'active'; ?>">Contact Messages</a>
                <a href="/admin?section=logs" class="sidebar-link <?php if ($section === 'logs') echo 'active'; ?>">Admin Logs</a>
            </nav>
            <a href="/admin?action=logout" class="sidebar-link">Logout</a>
        </aside>

        <main class="flex-1 p-8">
            <?php
            if (isset($_SESSION['flash_message'])) {
                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message']);
            }
            ?>
