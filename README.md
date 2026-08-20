# UIU CampusConnect — Laravel Edition

A ground-up rebuild of CampusConnect on Laravel 13 + Filament 5, MySQL, and a
custom Tailwind CSS v4 design system (terracotta `#c15f3c` on white, with a
cookie-persisted dark mode).

## Before you start — read this

This project was written in a sandboxed cloud environment with **no access to
Packagist or the npm registry**, so `composer install` and `npm install`
could not be run or verified here. Every PHP file has been checked with
`php -l` (syntax-clean) and cross-referenced by hand (route names, model
relationships, Blade tag balance), and the Filament v5 API calls were
verified against the current Filament docs — but the app has not been booted
end-to-end. Run the install steps below on your own machine and treat the
first boot as your first real test pass.

## Requirements

- PHP 8.3+ with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`
- Composer 2
- Node 20+ / npm
- MySQL 8+ or MariaDB 10.6+

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your MySQL credentials (`DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`), then:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build      # or `npm run dev` while working on the front end
php artisan serve
```

Visit `http://127.0.0.1:8000`. Sign in with any seeded account — all use
password `demo123`:

- Admin (Filament panel at `/admin`): `admin@uiu.ac.bd`
- Faculty (VC): `vc@uiu.ac.bd`
- Faculty (club moderator): `nahid.hossain@cse.uiu.ac.bd`
- Official (registrar): `registrar@uiu.ac.bd`
- Students: `011233001@bscse.uiu.ac.bd`, `011233002@bscse.uiu.ac.bd`

**Change or remove these before any real deployment.**

## What's here

- **Auth** — role-selecting signup (student/faculty/official), UIU-email-only
  validation, faculty/official accounts held `pending` until an admin
  approves them, throttled login (8 attempts / 15 min), forced password
  change for temporary passwords.
- **Feed** — posts with images/video/polls, reactions, threaded comments,
  save/share/hide/report, edit history.
- **Network** — role-gated connection rules (students↔students only;
  faculty↔faculty and official↔official connect instantly), follow, block.
- **Messages** — role-gated DMs with attachments and blocking.
- **Clubs, Events, Notices, Administration (section announcements), Groups,
  Marketplace, Lost & Found, Search, Notifications** — full CRUD, same
  role/authority-level rules as the rest of the app.
- **Admin** — Filament 5 panel at `/admin`: users, post-report moderation
  queue, clubs, events, notices, and a read-only audit log, plus a stats
  dashboard.
- **Caching** — unread message/notification counts and the admin dashboard
  stats are cached (`config/cache.php`); default store is `database` — swap
  to Redis in production by setting `CACHE_STORE=redis` and filling in the
  `REDIS_*` env vars.
- **Cookies** — the light/dark preference is stored in a plain (non-encrypted,
  1-year) cookie read server-side on every request, so the correct theme
  renders on the very first paint — no flash of the wrong theme. Toggle it
  from the sun/moon icon in the header.
- **Design** — every screen shares one Blade layout/component system
  (`resources/views/components/app-layout.blade.php`,
  `resources/css/app.css`). No placeholder or "helper" copy was added
  anywhere in the UI by design.

## Notes on the source tree

- All role/authority-level business rules live in `app/Services/SocialRules.php`
  — one auditable place instead of scattered checks.
- `app/Policies` covers post/comment ownership; `Gate::before` in
  `AppServiceProvider` gives admins a blanket bypass, matching the old app's
  "admin can do anything" behaviour.
- Migrations are organized by feature area (one file can create several
  related tables) rather than one-table-per-file, to keep the history
  readable.
