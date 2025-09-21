# E2CE — PHP CMS Starter (Windows/IIS ready, Apache compatible)

This is a lightweight CMS starter: **PHP 8.2+, MySQL 8, Bootstrap 5, jQuery**.
It runs without Composer, but includes a `composer.json` if you prefer PSR‑4 autoload.

## Quick start
1. Create a database and import `schema.sql`.
2. Copy the project to your server. Set the web root to the `public/` folder.
3. Duplicate `.env.example` to `.env` and set credentials.
4. Ensure `storage/` and `public/uploads/` are writable by the web server.
5. For IIS, use `public/web.config`. For Apache, use `public/.htaccess`.
6. Open `/admin` → login with the default admin (see `schema.sql` comment).

## Features
- Minimal **front controller** (`public/index.php`) and simple Router.
- Views with a base layout (Bootstrap from CDN).
- Admin auth (login/logout), session, CSRF protection.
- Post CRUD (draft/published/scheduled) with slug and meta fields.
- Robots.txt and dynamic sitemap.xml.
- Simple .env loader and PDO wrapper.
- Works without Composer; optionally use Composer autoload.

> This is a starter — extend controllers, models, and views as needed.
