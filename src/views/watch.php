<?php
// src/views/watch.php
require_once 'header.php';
?>

<script>
    document.title = "<?php echo htmlspecialchars($video['title']); ?> - MyTube";
</script>
<div class="flex flex-col lg:flex-row gap-4">
    <div class="w-full lg:w-2/3">
        <div class="video-container aspect-video" id="videoContainer">
            <video id="mainVideo" class="w-full h-full" src="/uploads/<?php echo htmlspecialchars($video['filename']); ?>"></video>
            <div class="controls">
                <div class="progress-bar" id="progressBar">
                    <div class="progress-bar-fill" id="progressBarFill"></div>
                </div>
                <div class="flex items-center">
                    <button class="control-btn" id="playPauseBtn">▶</button>
                    <div class="flex items-center space-x-2"><button class="control-btn" id="volumeBtn">🔊</button><input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.01" value="1"></div>
                    <div class="text-white text-xs ml-2"><span id="currentTime">00:00</span> / <span id="totalTime">00:00</span></div>
                    <div class="flex-grow"></div>
                    <button class="control-btn" id="fullscreenBtn">⛶</button>
                </div>
            </div>
        </div>
        <div class="box mt-4 p-4">
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($video['title']); ?></h1>
            <div class="flex justify-between items-center mt-2 text-gray-600 border-b pb-2">
                <div>
                    <span class="font-bold"><?php echo number_format($video['views']); ?> views</span>
                    <span class="mx-2 text-gray-400">•</span>
                    <span>Uploaded on <?php echo date('M d, Y', strtotime($video['upload_date'])); ?></span>
                </div>
                <div class="flex items-center space-x-4" id="likeDislikeContainer">
                    <button class="btn-classic like-btn" data-video-id="<?php echo $video['id']; ?>" data-like-type="1">Like (<span id="likeCount"><?php echo $likes; ?></span>)</button>
                    <button class="btn-classic dislike-btn" data-video-id="<?php echo $video['id']; ?>" data-like-type="-1">Dislike (<span id="dislikeCount"><?php echo $dislikes; ?></span>)</button>
                </div>
            </div>
            <div class="mt-3">
                <p class="mb-2">Uploaded by: <a href="/channel/<?php echo urlencode($video['username']); ?>" class="link-classic font-bold"><?php echo htmlspecialchars($video['username']); ?></a></p>
                <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
            </div>
        </div>
        <div class="box mt-4 p-4">
            <h2 class="text-lg font-bold mb-2">Comments</h2>
            <?php if (isset($_SESSION['user_id'])) : ?>
                <form class="mb-4" id="commentForm">
                    <input type="hidden" name="action" value="post_comment">
                    <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                    <textarea name="comment" id="commentText" class="w-full input-classic" rows="3" placeholder="Add a public comment..."></textarea>
                    <button type="submit" class="btn-classic mt-2">Comment</button>
                </form>
            <?php else : ?>
                <p class="mb-4"><a href="/login" class="link-classic">Log in</a> to post a comment.</p>
            <?php endif; ?>
            <div id="commentsContainer">
                <?php if (count($comments) > 0) : ?>
                    <?php foreach ($comments as $comment) : ?>
                        <div class="flex space-x-3 border-t py-3">
                            <img src="<?php echo get_avatar_url($comment['profile_picture']); ?>" alt="<?php echo htmlspecialchars($comment['username']); ?>" class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0 object-cover">
                            <div>
                                <p><a href="/channel/<?php echo urlencode($comment['username']); ?>" class="font-bold link-classic"><?php echo htmlspecialchars($comment['username']); ?></a> <span class="text-xs text-gray-500">(<?php echo date('M d, Y, g:i A', strtotime($comment['comment_date'])); ?>)</span></p>
                                <p class="mt-1"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p id="noCommentsMsg">No comments yet.</p>
                <?php endif; ?>
            </div>
            <div class="mt-4 flex justify-between items-center border-t pt-2">
                <?php if ($comments_page > 1) : ?>
                    <a href="/watch/<?php echo $video['video_id']; ?>?cp=<?php echo $comments_page - 1; ?>" class="btn-classic text-xs">&laquo; Previous</a>
                <?php else : ?>
                    <div></div>
                <?php endif; ?>
                <?php if ($total_comment_pages > 1) : ?>
                    <span class="text-xs text-gray-600">Page <?php echo $comments_page; ?> of <?php echo $total_comment_pages; ?></span>
                <?php endif; ?>
                <?php if ($comments_page < $total_comment_pages) : ?>
                    <a href="/watch/<?php echo $video['video_id']; ?>?cp=<?php echo $comments_page + 1; ?>" class="btn-classic text-xs">Next &raquo;</a>
                <?php else : ?>
                    <div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="w-full lg:w-1/3">
        <div class="box p-4">
            <h2 class="font-bold mb-2 text-lg">Up Next</h2>
            <div class="grid grid-cols-2 lg:grid-cols-1 gap-4">
                <?php if (!empty($related_videos)) : ?>
                    <?php foreach ($related_videos as $related_video) : ?>
                        <div class="box p-2 flex flex-col">
                            <a href="/watch/<?php echo htmlspecialchars($related_video['video_id']); ?>" class="aspect-video block mb-2">
                                <?php if (!empty($related_video['thumbnail'])) : ?>
                                    <img src="/uploads/<?php echo htmlspecialchars($related_video['thumbnail']); ?>" alt="<?php echo htmlspecialchars($related_video['title']); ?>" class="w-full h-full object-cover border border-gray-400">
                                <?php else : ?>
                                    <div class="w-full h-full bg-black flex items-center justify-center text-white text-xs border border-gray-400"></div>
                                <?php endif; ?>
                            </a>
                            <div class="flex-grow">
                                <a href="/watch/<?php echo htmlspecialchars($related_video['video_id']); ?>" class="link-classic font-bold text-sm leading-tight"><?php echo htmlspecialchars($related_video['title']); ?></a>
                                <p class="text-xs text-gray-600">by <a href="/channel/<?php echo urlencode($related_video['username']); ?>" class="link-classic"><?php echo htmlspecialchars($related_video['username']); ?></a></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo number_format($related_video['views']); ?> views</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="text-gray-600 col-span-2 lg:col-span-1">No other videos available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>
