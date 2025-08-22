<?php
// src/views/admin/dashboard.php
require_once 'header.php';
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

<?php
require_once 'footer.php';
?>
