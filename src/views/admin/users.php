<?php
// src/views/admin/users.php
require_once 'header.php';
?>

<h1 class="text-3xl font-bold mb-6">User Management</h1>
<form class="mb-4">
    <input type="hidden" name="section" value="users">
    <input type="text" name="search" placeholder="Search by username..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button>
</form>
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
            <?php foreach ($users as $user) : ?>
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
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-4 flex justify-between">
    <?php if ($current_page > 1) : ?>
        <a href="?section=users&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a>
    <?php endif; ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages) : ?>
        <a href="?section=users&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
