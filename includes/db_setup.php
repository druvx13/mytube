<?php
/**
 * MyTube — Database Setup & Migration Runner
 *
 * Call run_db_setup($db) once per bootstrap to ensure all tables exist
 * and any missing columns are added for existing installs.
 */

function run_db_setup(mysqli $db): void
{
    // -----------------------------------------------------------------
    // Core tables — use plain INT (display-width syntax is deprecated
    // in MySQL 8.0 and removed in MySQL 8.0.17+).
    // -----------------------------------------------------------------
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username        VARCHAR(50)  NOT NULL UNIQUE,
        email           VARCHAR(100) NOT NULL UNIQUE,
        password        VARCHAR(255) NOT NULL,
        is_admin        TINYINT      NOT NULL DEFAULT 0,
        is_banned       TINYINT      NOT NULL DEFAULT 0,
        profile_picture VARCHAR(255) NULL DEFAULT NULL,
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS videos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        video_id    VARCHAR(16)  NOT NULL UNIQUE,
        title       VARCHAR(255) NOT NULL,
        description TEXT,
        filename    VARCHAR(255) NOT NULL,
        thumbnail   VARCHAR(255) NULL,
        views       INT UNSIGNED NOT NULL DEFAULT 0,
        upload_date TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS comments (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        video_id     INT UNSIGNED NOT NULL,
        user_id      INT UNSIGNED NOT NULL,
        comment      TEXT         NOT NULL,
        comment_date TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS likes (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        video_id  INT UNSIGNED NOT NULL,
        user_id   INT UNSIGNED NOT NULL,
        like_type TINYINT      NOT NULL,
        UNIQUE KEY uq_video_user (video_id, user_id),
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        email       VARCHAR(100) NOT NULL,
        subject     VARCHAR(255) NOT NULL,
        message     TEXT         NOT NULL,
        is_read     TINYINT      NOT NULL DEFAULT 0,
        received_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS admin_logs (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_username VARCHAR(255),
        action         VARCHAR(255),
        target_info    TEXT,
        ip_address     VARCHAR(45),
        timestamp      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // -----------------------------------------------------------------
    // Migrations for existing installs (add columns if missing)
    // -----------------------------------------------------------------
    $existing_user_cols = [];
    $r = $db->query("SHOW COLUMNS FROM `users`");
    while ($row = $r->fetch_assoc()) {
        $existing_user_cols[] = $row['Field'];
    }
    if (!in_array('is_admin', $existing_user_cols)) {
        $db->query("ALTER TABLE `users` ADD `is_admin` TINYINT NOT NULL DEFAULT 0 AFTER `password`");
    }
    if (!in_array('is_banned', $existing_user_cols)) {
        $db->query("ALTER TABLE `users` ADD `is_banned` TINYINT NOT NULL DEFAULT 0 AFTER `is_admin`");
    }
    if (!in_array('profile_picture', $existing_user_cols)) {
        $db->query("ALTER TABLE `users` ADD `profile_picture` VARCHAR(255) NULL DEFAULT NULL AFTER `is_banned`");
    }

    $existing_video_cols = [];
    $vr = $db->query("SHOW COLUMNS FROM `videos`");
    while ($row = $vr->fetch_assoc()) {
        $existing_video_cols[] = $row['Field'];
    }
    if (!in_array('views', $existing_video_cols)) {
        $db->query("ALTER TABLE `videos` ADD `views` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `thumbnail`");
    }
}
