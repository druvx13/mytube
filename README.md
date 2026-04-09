# mytube

A retro-style video sharing site.

## Project structure

- `index.php` / `admin.php`: lightweight front controllers
- `app/Bootstrap`: startup and environment loading
- `app/Config`: centralized configuration (DB/env)
- `app/Infrastructure`: shared infrastructure components
- `legacy/`: migrated legacy application flows split from public entrypoints

## Requirements

- PHP 8.1+
- MySQL/MariaDB
- Composer 2+

## Setup

1. Install dependencies:
   ```bash
   composer install
   ```
2. Copy environment config:
   ```bash
   cp .env.example .env
   ```
3. Update `.env` with your database credentials.
4. Create an `uploads/` directory writable by your web server.
5. Serve the project root with PHP-enabled web server.

## Notes

- Runtime DB schema creation/migrations remain in legacy runtime modules.
- Existing URL behavior (`index.php` and `admin.php`) is preserved.
