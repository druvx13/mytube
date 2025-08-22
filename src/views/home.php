<?php
// src/views/home.php
require_once 'header.php';
?>

<h1 class="text-xl font-bold mb-4">Featured Videos</h1>
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
                    <p class="text-xs text-gray-600">by <a href="/channel/<?php echo urlencode($video['username']); ?>" class="link-classic"><?php echo htmlspecialchars($video['username']); ?></a></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo number_format($video['views']); ?> views</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-6 flex justify-between items-center">
        <?php if ($current_page > 1) : ?>
            <a href="/?p=<?php echo ($current_page - 1); ?>" class="btn-classic">&laquo; Previous</a>
        <?php else : ?>
            <div></div>
        <?php endif; ?>
        <?php if ($total_pages > 1) : ?>
            <span>Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
        <?php endif; ?>
        <?php if ($current_page < $total_pages) : ?>
            <a href="/?p=<?php echo ($current_page + 1); ?>" class="btn-classic">Next &raquo;</a>
        <?php else : ?>
            <div></div>
        <?php endif; ?>
    </div>
<?php else : ?>
    <p>No videos found.</p>
<?php endif; ?>

<?php
require_once 'footer.php';
?>
