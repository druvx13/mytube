document.addEventListener('DOMContentLoaded', () => {
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            if (!formData.get('comment').trim()) {
                alert('Comment cannot be empty.');
                return;
            }
            fetch('/ajax', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    const commentsContainer = document.getElementById('commentsContainer');
                    const noCommentsMsg = document.getElementById('noCommentsMsg');
                    if (noCommentsMsg) noCommentsMsg.remove();
                    const newComment = `
                        <div class="flex space-x-3 border-t py-3">
                            <img src="${data.comment.avatar_url}" alt="${data.comment.username}" class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0 object-cover">
                            <div>
                                <p><a href="/channel/${encodeURIComponent(data.comment.username)}" class="font-bold link-classic">${data.comment.username}</a> <span class="text-xs text-gray-500">(${data.comment.comment_date})</span></p>
                                <p class="mt-1">${data.comment.comment_text}</p>
                            </div>
                        </div>`;
                    commentsContainer.insertAdjacentHTML('afterbegin', newComment);
                    document.getElementById('commentText').value = '';
                } else {
                    alert(data.message || 'An error occurred.');
                }
            }).catch(error => console.error('Error:', error));
        });
    }
    const likeDislikeContainer = document.getElementById('likeDislikeContainer');
    if (likeDislikeContainer) {
        likeDislikeContainer.addEventListener('click', function(e) {
            const button = e.target.closest('button');
            if (button) {
                const formData = new FormData();
                formData.append('action', 'like_video');
                formData.append('video_id', button.dataset.videoId);
                formData.append('like_type', button.dataset.likeType);
                fetch('/ajax', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        document.getElementById('likeCount').textContent = data.likes;
                        document.getElementById('dislikeCount').textContent = data.dislikes;
                    } else {
                        alert(data.message || 'An error occurred.');
                    }
                }).catch(error => console.error('Error:', error));
            }
        });
    }
});
if (document.getElementById('videoContainer')) {
    const videoContainer = document.getElementById('videoContainer'),
        video = document.getElementById('mainVideo'),
        playPauseBtn = document.getElementById('playPauseBtn'),
        progressBar = document.getElementById('progressBar'),
        progressBarFill = document.getElementById('progressBarFill'),
        volumeBtn = document.getElementById('volumeBtn'),
        volumeSlider = document.getElementById('volumeSlider'),
        currentTimeEl = document.getElementById('currentTime'),
        totalTimeEl =.getElementById('totalTime'),
        fullscreenBtn = document.getElementById('fullscreenBtn');
    const togglePlay = () => {
        if (video.paused) {
            video.play();
            playPauseBtn.textContent = '❚❚';
        } else {
            video.pause();
            playPauseBtn.textContent = '▶';
        }
    };
    playPauseBtn.addEventListener('click', togglePlay);
    video.addEventListener('click', togglePlay);
    video.addEventListener('timeupdate', () => {
        const progress = (video.currentTime / video.duration) * 100;
        progressBarFill.style.width = `${progress}%`;
        currentTimeEl.textContent = formatTime(video.currentTime);
    });
    video.addEventListener('loadedmetadata', () => {
        totalTimeEl.textContent = formatTime(video.duration);
    });
    progressBar.addEventListener('click', (e) => {
        video.currentTime = (e.offsetX / progressBar.offsetWidth) * video.duration;
    });
    volumeSlider.addEventListener('input', (e) => {
        video.volume = e.target.value;
        video.muted = e.target.value == 0;
    });
    volumeBtn.addEventListener('click', () => {
        video.muted = !video.muted;
        if (video.muted) {
            volumeSlider.value = 0;
            volumeBtn.textContent = '🔇';
        } else {
            volumeSlider.value = video.volume;
            volumeBtn.textContent = '🔊';
        }
    });
    fullscreenBtn.addEventListener('click', () => {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            videoContainer.requestFullscreen().catch(err => {
                alert(`Error: ${err.message}`);
            });
        }
    });
    const formatTime = (seconds) => {
        const m = Math.floor(seconds / 60),
            s = Math.floor(seconds % 60);
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };
}
if (document.getElementById('thumbnailChooser')) {
    const videoFileInput = document.getElementById('video_file'),
        thumbnailChooser = document.getElementById('thumbnailChooser'),
        videoPreview = document.getElementById('videoPreview'),
        canvas = document.getElementById('canvas'),
        thumbnailPreview = document.getElementById('thumbnailPreview'),
        thumbnailScrubber = document.getElementById('thumbnailScrubber'),
        thumbnailDataInput = document.getElementById('thumbnailData'),
        ctx = canvas.getContext('2d');
    const captureFrame = (time) => {
        if (!isNaN(time) && typeof time !== 'undefined') {
            videoPreview.currentTime = time;
        }
    };
    videoFileInput.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (file) {
            videoPreview.src = URL.createObjectURL(file);
            thumbnailChooser.classList.remove('hidden');
        }
    });
    videoPreview.addEventListener('loadedmetadata', () => {
        canvas.width = videoPreview.videoWidth;
        canvas.height = videoPreview.videoHeight;
        thumbnailScrubber.max = videoPreview.duration;
        captureFrame(1);
    });
    thumbnailScrubber.addEventListener('input', () => {
        captureFrame(thumbnailScrubber.value);
    });
    videoPreview.addEventListener('seeked', () => {
        ctx.drawImage(videoPreview, 0, 0, canvas.width, canvas.height);
        const dataURL = canvas.toDataURL('image/jpeg', 0.8);
        thumbnailPreview.src = dataURL;
        thumbnailDataInput.value = dataURL;
    });
}
