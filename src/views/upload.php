<?php
// src/views/upload.php
require_once 'header.php';
?>

<script>
    document.title = "Upload Video - MyTube";
</script>
<div class="max-w-lg mx-auto box p-6">
    <h1 class="text-2xl font-bold mb-4">Upload a New Video</h1>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-4"><label for="title" class="block font-bold mb-1">Title</label><input type="text" id="title" name="title" class="w-full input-classic" required></div>
        <div class="mb-4"><label for="description" class="block font-bold mb-1">Description</label><textarea id="description" name="description" class="w-full input-classic" rows="5"></textarea></div>
        <div class="mb-4"><label for="video_file" class="block font-bold mb-1">Video File (MP4, WebM, OGG)</label><input type="file" id="video_file" name="video_file" class="w-full" accept="video/mp4,video/webm,video/ogg" required></div>
        <div id="thumbnailChooser" class="hidden mb-4">
            <label class="block font-bold mb-2">Choose Thumbnail</label>
            <div class="flex justify-center mb-2"><img id="thumbnailPreview" alt="Video frame preview" class="border-2 border-gray-400" style="width:240px; height:135px; object-fit:cover;"></div>
            <input type="range" id="thumbnailScrubber" class="w-full" min="0" max="100" step="0.1" value="1">
            <p class="text-center text-xs text-gray-600 mt-1">Move the slider to select a frame.</p>
            <video id="videoPreview" class="hidden"></video>
            <canvas id="canvas" class="hidden"></canvas><input type="hidden" name="thumbnail_data" id="thumbnailData">
        </div>
        <button type="submit" class="btn-classic">Upload Video</button>
    </form>
</div>

<?php
require_once 'footer.php';
?>
