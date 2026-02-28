<?php
/**
 * MyTube — Configuration Example
 *
 * Copy this file to config.php and fill in your own values.
 *
 *   cp config.example.php config.php
 */

// --- Database Credentials ---
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME',     'mytube');

// --- Upload Path (absolute) & URL (relative to web root) ---
define('UPLOADS_DIR', __DIR__ . '/uploads/');
define('UPLOADS_URL', 'uploads/');

// --- Application Settings ---
define('APP_NAME',    'MyTube');
define('APP_VERSION', '2.0');
