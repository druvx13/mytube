<?php
/**
 * MyTube — Helper Functions
 */

/**
 * Redirect to a URL and halt execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit();
}

/**
 * Generate a cryptographically random video ID (YouTube-style).
 *
 * Uses random_bytes() instead of str_shuffle() to ensure
 * unpredictability.
 */
function generate_video_id(int $length = 11): string
{
    $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max    = strlen($chars) - 1;
    $result = '';
    $bytes  = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[ord($bytes[$i]) % ($max + 1)];
    }
    return $result;
}

/**
 * Return a URL for a user's avatar, falling back to an inline SVG
 * data-URI when no picture is stored.
 */
function get_avatar_url(?string $filename): string
{
    if ($filename && file_exists(UPLOADS_DIR . $filename)) {
        return UPLOADS_URL . htmlspecialchars($filename);
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#cccccc">'
         . '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2'
         . 'c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Return the current CSRF token, creating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify that the POST request contains a valid CSRF token.
 * Dies with a 403 on failure.
 */
function verify_csrf(): void
{
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
