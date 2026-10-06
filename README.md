<p align="center">
  <img src="public/img/logo-192.png" width="96" alt="UIU CampusConnect logo">
</p>

<h1 align="center">UIU CampusConnect</h1>

<p align="center">
  A unified, verified social platform for the students, faculty, Registrar's Office and administrators of United International University.
</p>

<p align="center">
  Software Engineering Laboratory (CSE 3422), Section J
</p>

---

## About

UIU CampusConnect is a social platform built only for United International University. Every account belongs to a verified member of the university, and each person sees the platform through their role: student, faculty member, Registrar's Office or administrator.

The platform is built from the university's own structure. When the Registrar's Office records a course section, a class group is created for it, with the teacher as owner and the enrolled students as members. Clubs get their own groups in the same way. On top of that, the platform provides a campus feed, a network, private messaging, official notices, campus tools and an AI campus assistant.

## Features

- Accounts, sign-in and account recovery, with administrator approval
- Campus feed and posts: polls, reactions, comments and mentions
- Reporting and moderation
- Network and faculty connection applications
- Groups, invitations and posting rules
- Class groups and coursework: materials, assignments and submissions
- Private messaging and live class chat
- Clubs and events
- Course sections and official notices from the Registrar's Office
- Campus assistant (AI) that only uses data the signed-in user may see
- Student tools: deadlines, academic calendar, shuttle schedule, marketplace, lost and found
- Faculty academic profiles
- Admin Center and data panel
- 3D campus landing page with day and night themes

## Technology

| Area | Technology |
|---|---|
| Back end | PHP 8.3, Laravel 13 |
| Front end | Blade with Alpine.js, React 19 through Inertia, Tailwind CSS v4 |
| Database | MySQL 8 |
| Real time | Laravel Reverb and Laravel Echo |
| Admin panel | Filament 5 |
| 3D graphics | Three.js |
| Build tool | Vite 6 |
| Testing | Selenium WebDriver |
| Version control | Git and GitHub |

## Running it locally

Requirements: PHP 8.3, Composer 2, Node.js 20 and MySQL 8 (for example through XAMPP).

```bash
git clone https://github.com/shuaib0606-bit/uiu-campusconnect.git
cd uiu-campusconnect
composer install
npm install
cp .env.example .env          # then enter your database name, user and password
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve             # open http://127.0.0.1:8000
```

Live class chat through Laravel Reverb is optional; without it, chat falls back to polling.

`php artisan migrate --seed` creates a few demo accounts with demo passwords. Change or remove them before any real use.

The `.env` file, uploads, logs and local databases are never committed. API keys for the campus assistant go in `.env` only.

## Documentation

- `docs/` holds the system documentation, the code tour, the demonstration script and the project reports.
- `tests/selenium/` holds the automated browser tests and their summary.

## Team

| Name | Student ID |
|---|---|
| Sindeed Shuaib Arpon | 0112330261 |
| Tanjim Wahid | 0112410146 |

Course teacher: Sajid Ahmed Chowdhury, Department of Computer Science and Engineering, United International University.
