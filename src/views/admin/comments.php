<?php
// src/views/admin/comments.php
require_once 'header.php';
?>

<h1 class="text-3xl font-bold mb-6">Comment Moderation</h1>
<form class="mb-4">
    <input type="hidden" name="section" value="comments">
    <input type="text" name="search" placeholder="Search in comments..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button>
</form>
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
            <?php foreach ($comments as $comment) : ?>
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
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-4 flex justify-between">
    <?php if ($current_page > 1) : ?>
        <a href="?section=comments&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a>
    <?php endif; ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages) : ?>
        <a href="?section=comments&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
