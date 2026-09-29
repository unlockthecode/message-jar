# Deployment — InfinityFree

How to deploy Message Jar to a live HTTPS URL on InfinityFree's free tier.

## Overview

```
Browser → HTTPS → InfinityFree (Apache + PHP 8.4 + MySQL)
              ↘ ImageKit (images)
              ↘ YouTube (video embeds)
```

The PHP server never touches image or video bytes. The web root is
`htdocs/public/` — `src/`, `config/`, and `.env` live outside it, so
they aren't browser-reachable.

## Prerequisites

- Working local install (see README).
- Free ImageKit account with `IMAGEKIT_URL_ENDPOINT` known.
- Free InfinityFree account.

## Step 1 — InfinityFree hosting account

1. Sign up at https://infinityfree.com, verify email.
2. Client area → **Create Account**.
3. Choose a free subdomain or attach a custom domain.
4. Note these from the account panel:
   - Main Domain
   - FTP Hostname (usually `ftpupload.net`)
   - FTP Username / Password
   - MySQL Hostname (e.g. `sql302.infinityfree.com`)
   - MySQL Username / Password

## Step 2 — MySQL database

1. Open the hosting account → sidebar → **MySQL Databases**.
2. **Create Database**, name it (e.g. `messagejar`). InfinityFree
   prefixes it, giving e.g. `if0_XXXXXXX_messagejar`.
3. Set a strong password.

Record:

```
DB_HOST=sql302.infinityfree.com
DB_PORT=3306
DB_NAME=if0_XXXXXXX_messagejar
DB_USER=if0_XXXXXXX
DB_PASS=<the password you set>
```

## Step 3 — Production `.env`

Create `.env.production` in the project root (never commit it):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_HOST=sql302.infinityfree.com
DB_PORT=3306
DB_NAME=if0_XXXXXXX_messagejar
DB_USER=if0_XXXXXXX
DB_PASS=REPLACE_WITH_DB_PASSWORD

SESSION_NAME=mj_session
SESSION_LIFETIME=2592000

IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/your-endpoint
```

`APP_DEBUG` must be `false` in production. Flip to `true` temporarily
only when debugging a live issue, then revert.

## Step 4 — Root `.htaccess`

Create `.htaccess` at the project root:

```apache
Options -MultiViews
RewriteEngine On

# Force HTTPS (uncomment AFTER the SSL certificate is confirmed working)
# RewriteCond %{HTTPS} off
# RewriteCond %{HTTP:X-Forwarded-Proto} !https
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route everything through public/
RewriteCond %{REQUEST_URI} !^/public/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/$1 [L]
```

The HTTPS block stays commented out until Step 10.

## Step 5 — Root `index.php` shim

InfinityFree's Apache looks for `htdocs/index.php` on `/`. Our entry
point is `public/index.php`, so we need a shim at the root.

Create `index.php` at the project root:

```php
<?php
declare(strict_types=1);

// Root shim for InfinityFree: Apache serves htdocs/ and requires
// an index file there. Real entry point is public/index.php.
require __DIR__ . '/public/index.php';
```

## Step 6 — Upload via FTP

Use FileZilla. First enable **Server → Force showing hidden files**.

Connect:

- Host: `ftpupload.net`
- Username / Password: your FTP credentials
- Port: 21

On the remote side, enter `htdocs/`. Delete any placeholder file.

Drag these from the local project root into `htdocs/`:

- `config/`
- `database/`
- `docs/`
- `public/`
- `scripts/`
- `src/`
- `templates/`
- `.htaccess` (root)
- `index.php` (root shim)
- `.env.production` (will be renamed)

Do **not** upload `.git/`, `logs/`, `.env`, `.gitignore`, `README.md`,
`LICENSE`.

Then rename remote `.env.production` → `.env`.

Remote `htdocs/` should contain:

```
htdocs/
├── .env
├── .htaccess
├── index.php            ← the shim
├── config/
├── database/
├── docs/
├── public/
├── scripts/
├── src/
└── templates/
```

## Step 7 — Import schema

1. Client area → **MySQL Databases** → **Admin** (phpMyAdmin).
2. **Import** tab → choose `database/schema.sql` → **Go**.

Verify five tables exist: `users`, `jars`, `messages`,
`message_views`, `login_attempts`.

## Step 8 — Create the two accounts

InfinityFree has no shell, so generate hashes locally and insert them
via SQL.

Locally:

```bash
php scripts/create-user.php yourname admin
php scripts/create-user.php hername user
```

Grab the hashes:

```sql
SELECT username, password_hash, role FROM users;
```

In production phpMyAdmin → **SQL**:

```sql
INSERT INTO users (username, password_hash, role) VALUES
('yourname', '$argon2id$...admin hash...', 'admin'),
('hername',  '$argon2id$...user hash...',  'user');
```

Verify with `SELECT id, username, role FROM users;`.

## Step 9 — Test the site

Visit `http://your-domain.example/`. If it doesn't load, set
`APP_DEBUG=true` in `htdocs/.env`, reload, read the error, then revert.

Checklist:

- [ ] Login page loads
- [ ] Admin login works
- [ ] Dashboard shows jars
- [ ] Jar draws a message on tap
- [ ] Image, YouTube, and external links render
- [ ] Scheduled (future-unlock) messages stay hidden
- [ ] Non-admin cannot reach `/admin/*` (403)
- [ ] CSRF: POST without token → 403

## Step 10 — Enable HTTPS

1. Client area → **SSL Certificates** → add one for your domain
   (Let's Encrypt or ZeroSSL).
2. Wait up to 30 min for issuance.
3. Once `https://` shows a padlock, uncomment the HTTPS block in
   `htdocs/.htaccess` and re-upload.

## Step 11 — HSTS (optional, 24h after HTTPS works)

In `src/bootstrap.php`, uncomment:

```php
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```

Upload. HSTS is one-way: browsers will refuse HTTP for a year.

## Step 12 — Verify no secrets are exposed

Each of these must return 404 or 403:

- `/.env`
- `/config/config.php`
- `/src/db.php`
- `/database/schema.sql`
- `/scripts/create-user.php`

If any file contents are visible, stop and fix `.htaccess`.

## Common issues

| Symptom | Fix |
|---|---|
| 404 on everything | Verify `htdocs/.htaccess` and `htdocs/index.php` exist |
| 500 on everything | Set `APP_DEBUG=true`, read the real error, revert |
| 500 on `/` only | Confirm `RewriteCond %{REQUEST_URI} !^/public/` is present |
| "No index file found" | Upload the root `index.php` shim |
| Login fails with correct password | Re-copy hash from local DB and re-INSERT |
| Session doesn't persist | Enable SSL; uncomment HTTPS block in `.htaccess` |
| Images broken | Set `IMAGEKIT_URL_ENDPOINT` in `.env` |
| Site unreachable | Check https://status.infinityfree.com/ |

## Not included

- Custom PHP upload system (images go through ImageKit dashboard).
- CI/CD (FTP is fine for a personal project).
- Cron jobs (InfinityFree free tier doesn't support them; scheduled
  messages are evaluated at request time).
- Composer (no external PHP dependencies).