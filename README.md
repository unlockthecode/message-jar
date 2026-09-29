# Message Jar

A private, romantic web app where my partner logs in, taps a jar, and
receives a random message I've written for her. Each jar is a themed
collection — "When You Miss Me", "Good Night", "Anniversary", and so on.
Messages can include a photo, a YouTube video, and a link, and can be
scheduled to unlock on a specific date.

Built as a personal project to learn PHP fundamentals, security
best practices, and deployment on free hosting.

## Features

- **Romantic jar interface** — a jar illustration that shakes and
  opens with a lid-pop animation when tapped.
- **Random message selection** — prefers unseen messages over recently
  seen ones, so she never sees the same note twice in a row.
- **Scheduled messages** — unlock and expiration dates enforced
  server-side, not just hidden in the UI.
- **Rich messages** — each message can contain text, an ImageKit-hosted
  image, an embedded YouTube video, and an HTTPS link.
- **Full admin panel** — create, edit, delete, reorder, and
  activate/deactivate jars and messages. Mobile-friendly.
- **Dashboard with live stats** — jar/message counts, recent draws,
  and expired-message warnings.
- **Two-account model** — no public registration. Accounts are
  created via a CLI script.

## Technology Stack

- **Backend:** PHP 8.x (no framework)
- **Database:** MySQL 8.x / MariaDB 10.4+
- **Frontend:** HTML, CSS (no framework), vanilla JavaScript
- **Image hosting:** [ImageKit](https://imagekit.io) CDN
- **Video:** YouTube privacy-enhanced embeds (`youtube-nocookie.com`)
- **Deployment:** InfinityFree (free PHP + MySQL hosting)

No Composer packages, no external CDNs, no analytics, no trackers.

## Security

This is a private two-user application. Security is taken seriously
but no system is "unhackable." The model:

- **Argon2id** password hashing (falls back to bcrypt)
- **CSRF protection** on every state-changing request
- **Prepared statements** everywhere via PDO (no string concatenation)
- **Session hardening**: `use_strict_mode`, HttpOnly cookies,
  SameSite=Lax, Secure in production, regeneration on login
- **Login rate limiting**: 5 attempts per IP per 15 minutes
- **Strict Content-Security-Policy** — `default-src 'none'`, no
  `'unsafe-inline'`
- **XSS protection**: every output escaped via a single `e()` helper
- **URL validation**: HTTPS-only external links; ImageKit-only images;
  YouTube IDs validated by regex before embedding
- **Authorization enforced server-side** — the frontend never decides

See [`docs/security-checklist.md`](docs/security-checklist.md) for the
full audit list.

## Local Development Setup

### Requirements

- Windows, macOS, or Linux
- [Laragon](https://laragon.org) (recommended on Windows) or any
  LAMP/WAMP/MAMP stack with PHP 8.0+ and MySQL 8+
- Git

### Steps

1. **Clone the repo**

   ```bash
   git clone https://github.com/yourname/message-jar.git
   cd message-jar
   ```

2. **Create the database**

   In Laragon (or phpMyAdmin / mysql CLI):

   ```sql
   CREATE DATABASE message_jar
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

3. **Load the schema**

   ```bash
   mysql -u root message_jar < database/schema.sql
   ```

4. **Create your `.env`**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` and set `DB_USER`, `DB_PASS`, and
   `IMAGEKIT_URL_ENDPOINT` for your environment.

5. **Configure your web root**

   Point your virtual host's document root at `public/`, not the
   project root. In Laragon, use `Menu → Apache → sites-enabled` and
   set `DocumentRoot` to `.../message-jar/public`.

   **This step is not optional.** If the document root is the project
   root, `.env`, `src/`, and `config/` become browser-accessible.

6. **Create the two accounts**

   ```bash
   php scripts/create-user.php yourname admin
   php scripts/create-user.php hername user
   ```

   Each command prompts for a password. Use at least 12 characters.

7. **Visit the site**

   Open `http://message-jar.test` (or whatever host you configured).

## ImageKit Setup

Images are hosted on ImageKit and loaded directly from their CDN. The
PHP server never stores or proxies image files.

1. Create a free account at https://imagekit.io.
2. Note your URL endpoint (e.g. `https://ik.imagekit.io/your-endpoint`).
3. Put it in `.env` as `IMAGEKIT_URL_ENDPOINT`.
4. In the ImageKit dashboard, upload images and copy their URLs.
5. Paste the URLs into the admin message editor.

Only URLs under **your** endpoint are accepted. Other people's
ImageKit URLs, HTTP URLs, and non-ImageKit hosts are rejected at
both save and render time.

**Limitations to know:**

- Free-tier bandwidth is metered. Fine for a two-user site.
- Files are effectively public on the free tier. Don't upload
  anything sensitive.
- Displayed images cannot be made uncopyable. This is fine for
  non-sensitive photos.

## Deployment

Deployed for free on [InfinityFree](https://infinityfree.net).
The architecture:

```
Browser
  ↓
InfinityFree (PHP 8 + MySQL)
  ↓
MySQL database

Browser
  ↓
ImageKit CDN  (images)

Browser
  ↓
YouTube       (video embeds)
```

Full deployment walkthrough: see [`docs/deployment.md`](docs/deployment.md).

## Project Structure

```
message-jar/
├── public/              # Web root — the ONLY browser-accessible folder
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── jar.php
│   ├── jar-draw.php     # JSON endpoint
│   ├── admin/           # Admin panel
│   └── assets/          # CSS + JS
├── src/                 # Application logic (not web-accessible)
├── templates/           # Reusable HTML partials
├── config/              # Configuration loader
├── database/            # Schema + seed
├── scripts/             # CLI-only utilities
├── docs/                # Additional documentation
└── logs/                # Runtime logs (gitignored)
```

## Database Schema

Five tables:

- **`users`** — the two accounts, with a `role` of `admin` or `user`.
- **`jars`** — themed containers with emoji, color, display order.
- **`messages`** — belong to a jar; text, optional image/video/link,
  optional unlock/expiration dates.
- **`message_views`** — records which user drew which message when.
  Used to avoid repeats.
- **`login_attempts`** — IP, username, timestamp, success. Used for
  rate limiting.

See [`database/schema.sql`](database/schema.sql) for the full schema.

## Development Philosophy

- **No framework.** Understanding fundamentals matters more than
  abstraction for a project this size.
- **One bootstrap.** Every entry point starts the same way.
- **Escape at the edge.** Every output goes through `e()`.
- **Server-side authority.** JavaScript is for UX; PHP decides.
- **No secrets in the repo.** `.env` is gitignored; `.env.example`
  documents the keys.

## Future Improvements

Ideas for later, deliberately not in v1:

- Relationship timeline
- Shared memories / photo album
- "Open when…" scheduled letters
- Anniversary and birthday countdowns
- Optional in-app image upload via ImageKit's server-side API
- Encrypted private content

## License

MIT — see [LICENSE](LICENSE).

## Acknowledgements

Built for someone specific. If you're reading this and building your
own version, I hope it brings someone joy.