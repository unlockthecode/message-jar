# Security Checklist

Run through this before every deploy.

## Code audit

- [ ] `grep` for output without escaping — every `<?=` on dynamic data must go through `e()`
- [ ] `grep` for `query(` — no user input concatenated into SQL
- [ ] Every `$_POST` / `$_GET` value is validated or cast before use
- [ ] Every POST endpoint has `require_*` + `is_post()` + `csrf_verify()` in that order
- [ ] No new external `<script>`, `<link>`, or `<iframe>` sources without updating CSP
- [ ] No secrets in `src/`, `public/`, `templates/`, `config/` — all in `.env`

## Endpoint tests

- [ ] Logged out → all private URLs redirect to login (or 401)
- [ ] Non-admin logged in → all `/admin/*` return 403
- [ ] Admin logged in → everything works
- [ ] POST without CSRF token → 403
- [ ] POST with wrong method (GET) → 405
- [ ] `/scripts/*.php` unreachable from browser
- [ ] `/.env`, `/src/*`, `/config/*`, `/database/*` unreachable from browser

## Headers

- [ ] `Content-Security-Policy` present on all HTML responses
- [ ] `X-Content-Type-Options: nosniff` present
- [ ] `Referrer-Policy: strict-origin-when-cross-origin` present
- [ ] `X-Frame-Options: DENY` present
- [ ] `Strict-Transport-Security` present (production only, after HTTPS is confirmed)

## Session

- [ ] Cookie has `HttpOnly` (always)
- [ ] Cookie has `Secure` (production only)
- [ ] Cookie has `SameSite=Lax`
- [ ] Session ID changes after login
- [ ] Session ID changes after logout
- [ ] Fake session ID is rejected

## Error handling

- [ ] In production mode, forced error shows generic message (no stack trace)
- [ ] Error appears in `logs/php-error.log`
- [ ] No credentials or secrets appear in HTML output

## CSP regression

- [ ] Login page loads without CSP violations
- [ ] Dashboard loads without CSP violations
- [ ] Jar page with image loads (ImageKit allowed)
- [ ] Jar page with YouTube loads (frame allowed)
- [ ] Console shows no CSP errors

## Dependency check

- [ ] No new Composer packages without review
- [ ] No new external domains called without a CSP entry
- [ ] No new files in `public/` that shouldn't be exposed