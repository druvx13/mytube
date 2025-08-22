<?php
// src/views/account.php
require_once 'header.php';
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
    <?php if (count($videos) > 0) : ?>
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="text-left p-2">Title</th>
                    <th class="text-left p-2">Uploaded</th>
                    <th class="text-left p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $video) : ?>
                    <tr class="border-b">
                        <td class="p-2"><a href="/watch/<?php echo $video['video_id']; ?>" class="link-classic"><?php echo htmlspecialchars($video['title']); ?></a></td>
                        <td class="p-2"><?php echo date('M d, Y', strtotime($video['upload_date'])); ?></td>
                        <td class="p-2">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this video?');">
                                <input type="hidden" name="action" value="delete_video">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="link-classic text-red-600">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>You have not uploaded any videos yet. <a href="/upload" class="link-classic">Upload one now!</a></p>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>
