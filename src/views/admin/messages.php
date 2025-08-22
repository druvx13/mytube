<?php
// src/views/admin/messages.php
require_once 'header.php';
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
            <?php foreach ($messages as $msg) : ?>
                <tr class="<?php if (!$msg['is_read']) echo 'font-bold'; ?>">
                    <td><?php echo htmlspecialchars($msg['name']) . ' &lt;' . htmlspecialchars($msg['email']) . '&gt;'; ?></td>
                    <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($msg['received_at'])); ?></td>
                    <td><?php echo $msg['is_read'] ? 'Read' : 'Unread'; ?></td>
                    <td>
                        <button onclick="alert('<?php echo htmlspecialchars(addslashes($msg['message'])); ?>')" class="btn-sm btn-blue">View</button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="toggle_message_read">
                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                            <button type="submit" class="btn-sm <?php echo $msg['is_read'] ? 'btn-green' : 'btn-blue'; ?>"><?php echo $msg['is_read'] ? 'Mark Unread' : 'Mark Read'; ?></button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete_message">
                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                            <button type="submit" class="btn-sm btn-red">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-4 flex justify-between">
    <?php if ($current_page > 1) : ?>
        <a href="?section=messages&p=<?php echo $current_page - 1; ?>" class="p-2 bg-gray-300 rounded">Previous</a>
    <?php endif; ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages) : ?>
        <a href="?section=messages&p=<?php echo $current_page + 1; ?>" class="p-2 bg-gray-300 rounded">Next</a>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
