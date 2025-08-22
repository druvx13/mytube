<?php
// src/views/channel.php
require_once 'header.php';
?>

<script>
    document.title = "<?php echo htmlspecialchars($channel_user['username']); ?>'s Channel - MyTube";
</script>
<div class="box p-6 mb-4">
    <div class="flex flex-col md:flex-row gap-6 items-center">
        <div class="flex-shrink-0">
            <img src="<?php echo get_avatar_url($channel_user['profile_picture']); ?>" alt="<?php echo htmlspecialchars($channel_user['username']); ?>'s Profile Picture" class="w-32 h-32 rounded-full border-4 border-gray-200 object-cover">
        </div>
        <div class="flex-grow">
            <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($channel_user['username']); ?>'s Channel</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 text-gray-600">
                <div><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($channel_user['created_at'])); ?></div>
                <div><strong>Videos Uploaded:</strong> <?php echo number_format($stats['video_count']); ?></div>
                <div class="md:col-span-2"><strong>Total Video Views:</strong> <?php echo number_format($stats['total_views'] ?? 0); ?></div>
            </div>
        </div>
    </div>
</div>
<h2 class="text-xl font-bold mb-4">Videos</h2>
<?php if (count($videos) > 0) : ?>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <?php foreach ($videos as $video) : ?>
            <div class="box p-2 flex flex-col">
                <a href="/watch/<?php echo htmlspecialchars($video['video_id']); ?>" class="aspect-video block mb-2">
                    <?php if (!empty($video['thumbnail'])) : ?>
                        <img src="/uploads/<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" class="w-full h-full object-cover border border-gray-400">
                    <?php else : ?>
                        <div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400">No Thumbnail</div>
                    <?php endif; ?>
                </a>
                <div class="flex-grow">
                    <a href="/watch/<?php echo htmlspecialchars($video['video_id']); ?>" class="link-classic font-bold"><?php echo htmlspecialchars($video['title']); ?></a>
                    <p class="text-xs text-gray-500 mt-1"><?php echo number_format($video['views']); ?> views</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="box p-6">
        <p>This user has not uploaded any videos yet.</p>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
