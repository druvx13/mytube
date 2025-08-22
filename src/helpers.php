<?php
// src/helpers.php

function redirect($url)
{
    header("Location: " . $url);
    exit();
}

function generate_video_id($length = 11)
{
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
}

function get_avatar_url($filename)
{
    if ($filename && file_exists('uploads/' . $filename)) {
        return '/uploads/' . htmlspecialchars($filename);
    } else {
        // Return a default SVG icon encoded in Base64 to keep it self-contained
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#cccccc"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
?>
