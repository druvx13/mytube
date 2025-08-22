<?php
// src/views/admin/videos.php
require_once 'header.php';
?>

<h1 class="text-3xl font-bold mb-6">Video Moderation</h1>
<form class="mb-4">
    <input type="hidden" name="section" value="videos">
    <input type="text" name="search" placeholder="Search by video title..." class="p-2 border rounded" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="p-2 bg-blue-500 text-white rounded">Search</button>
</form>
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
            <?php foreach ($videos as $video) : ?>
                <tr>
                    <td><img src="/uploads/<?php echo htmlspecialchars($video['thumbnail']); ?>" class="w-24 h-auto" onerror="this.style.display='none'"></td>
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
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-4 flex justify-between">
    <?php if ($current_page > 1) : ?>
        <a href="?section=videos&p=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Previous</a>
    <?php endif; ?>
    <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages) : ?>
        <a href="?section=videos&p=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>" class="p-2 bg-gray-300 rounded">Next</a>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
