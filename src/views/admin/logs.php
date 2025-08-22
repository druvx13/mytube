<?php
// src/views/admin/logs.php
require_once 'header.php';
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
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo $log['timestamp']; ?></td>
                    <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['target_info']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-4 flex justify-between">
    <?php if ($current_page > 1) : ?>
        <a href="?section=logs&p=<?php echo $current_page - 1; ?>" class="p-2 bg-gray-300 rounded">Previous</a>
    <?php endif; ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages) : ?>
        <a href="?section=logs&p=<?php echo $current_page + 1; ?>" class="p-2 bg-gray-300 rounded">Next</a>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
