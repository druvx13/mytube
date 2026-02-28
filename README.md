# MyTube

A self-hosted retro-style video sharing platform inspired by the simplicity of early-era YouTube.  
**Version 2.0** — single PHP app, no Composer or npm required.

---

## Features

- **Video upload & playback** — powered by [Video.js 8](https://videojs.com/) (MP4, WebM, OGG, MOV)
- **Thumbnail selection** — scrub a video frame in-browser before uploading
- **Upload progress bar** — real-time XHR upload feedback
- **User accounts** — sign up, log in, profile pictures, account page
- **Likes / dislikes** — AJAX-powered, no page reload
- **Comments** — AJAX post + server-side pagination
- **Channel pages** — per-user public pages with stats
- **Search** — full-text search across title & description
- **Admin panel** — manage users, videos, comments, contact messages, and admin logs
- **Responsive design** — mobile-first layout with hamburger navigation

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.1 |
| MySQL / MariaDB | ≥ 5.7 / 10.4 |
| Web server | Apache (with `mod_rewrite` + `mod_headers`) or Nginx |

> No Composer, no npm, no build step.  
> All libraries load from CDN automatically:
> - **Tailwind CSS v3** (Play CDN — no build step)
> - **Video.js v8.23.7** (via jsDelivr)

---

## Quick Setup

### 1. Clone / Download

```bash
git clone https://github.com/druvx13/mytube.git
cd mytube
```

### 2. Configure the database

Copy the example config and fill in your credentials:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME',     'mytube');
```

### 3. Create the database

Create an empty database in MySQL/MariaDB:

```sql
CREATE DATABASE mytube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The application creates all required tables automatically on first visit.

### 4. Set up the uploads directory

```bash
# Already tracked via uploads/.gitkeep
# Just ensure the web server can write to it:
chmod 755 uploads/
```

### 5. Configure the admin password

Generate a bcrypt hash for your desired admin password:

```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Paste the resulting hash into `admin.php`:

```php
define('ADMIN_PASS_HASH', '$2y$12$...');  // your hash here
define('ADMIN_USER',      'admin');        // or any username
```

### 6. Point your web server at the project root

For Apache, the included `.htaccess` sets security headers and blocks direct access to `includes/`.

For Nginx, add an equivalent location block:

```nginx
location ~ ^/(includes|config\.php) {
    deny all;
}
```

### 7. Visit the site

Navigate to `http://your-server/` and start uploading!

---

## Project Structure

```
mytube/
├── config.php           ← DB credentials (git-ignored, not committed)
├── config.example.php   ← Safe template — copy to config.php
├── index.php            ← Main frontend application
├── admin.php            ← Admin panel
├── .htaccess            ← Apache security headers & rewrite rules
├── .gitignore
├── README.md
├── includes/
│   ├── functions.php    ← Helper functions (redirects, CSRF, video ID gen, avatars)
│   ├── db_setup.php     ← DB table creation & migrations
│   └── ajax.php         ← AJAX endpoint handler (comments, likes)
└── uploads/
    └── .gitkeep         ← Placeholder — uploaded files are git-ignored
```

---

## Admin Panel

Access the admin panel at `/admin.php`.

| Section | Description |
|---|---|
| Dashboard | Site-wide stats (users, videos, views, comments) |
| User Management | Search users, ban / unban accounts |
| Video Moderation | Browse and delete videos |
| Comment Moderation | Browse and delete comments |
| Contact Messages | View, mark as read/unread, delete |
| Admin Logs | Full audit log of admin actions |

Admin sessions expire after **30 minutes** of inactivity.

---

## Security Notes

- All forms are protected by **CSRF tokens** (SameSite + hash_equals)
- File uploads are validated by **MIME type** (using `finfo`), not just file extension
- Passwords are hashed with **bcrypt** (cost 12)
- SQL queries use **prepared statements** throughout
- Session ID is **regenerated** on login to prevent fixation
- The `includes/` directory and `config.php` are blocked from direct web access via `.htaccess`

---

## Video Player

Videos are played using **[Video.js 8](https://videojs.com/)**, a full-featured open-source HTML5 video player that supports:

- Adaptive playback rates (0.5×, 1×, 1.25×, 1.5×, 2×)
- Keyboard shortcut: **Space** toggles play/pause
- Fully accessible (ARIA-compliant)
- Responsive / fluid layout
- All formats the browser supports natively (MP4 H.264, WebM VP8/VP9, OGG Theora)

---

## Changelog

### v2.0
- **Reorganised** into `includes/` directory (functions, DB setup, AJAX handler)
- **Extracted** `config.php` — DB credentials no longer live in the main application files
- **Replaced** custom video player with **Video.js 8.23.7**
- **Added** responsive mobile navigation with hamburger menu
- **Added** CSRF protection to all state-changing forms
- **Added** upload progress bar (XHR with progress events)
- **Secured** file uploads with `finfo` MIME-type validation
- **Updated** password hashing to `PASSWORD_BCRYPT` with cost 12
- **Fixed** `generate_video_id()` to use `random_bytes()` (cryptographically secure)
- **Removed** deprecated `INT(11)` display-width syntax (MySQL 8.0.17+)
- **Added** `.htaccess` security headers (X-Frame-Options, X-Content-Type-Options, etc.)
- **Added** `.gitignore` (ignores `config.php` and `uploads/*`)
- WebP avatar/thumbnail support added
- `utf8mb4` charset enforced on all DB connections and tables

### v1.0
- Initial monolithic release

---

## License

See [LICENSE](LICENSE).

## Author

Developed by **DK** ([druvx13](https://github.com/druvx13)).
