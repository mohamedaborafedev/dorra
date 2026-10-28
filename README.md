# Dorra Design Library

Project reports: [فهرس تقارير التنفيذ والاختبارات والأداء](docs/reports/README.md).

Admins can manage accounts at `/users` (list, create, view, edit and delete). This
menu and all endpoints are blocked for Viewers. New passwords require confirmation
and at least 8 characters; leave the password blank when editing to retain it.
All write forms use CSRF protection. See [users and CSRF verification](docs/reports/users-and-csrf.md).

The current sample dataset uses ten shared local clothing images. Sources, assignment
and repeat benchmark results are in [the clothing report](docs/reports/clothing-performance.md).
These shared files are retained when a design is deleted or its thumbnail is replaced.

Built on the LaravelDaily Blade starter kit. The library replaces `/dashboard` after
login. Admins can create, view, edit and permanently delete designs; Viewers can
browse and view Published designs only; Admins also see Draft designs. Permissions are enforced on every
management request, not only by hiding buttons.

## Local setup

Verified runtime: PHP 8.4.12, Laravel 12.70.2, MySQL 8.4.3. Use MySQL 8.0.16+.
Create an empty database for a new installation. Copy `.env.example` to `.env`
only if `.env` does not already exist, and set your own database credentials.
Preserve existing databases and environment configuration.

```sh
composer install
npm ci
php artisan key:generate # new installations only
php artisan migrate
php artisan db:seed
php artisan db:seed --class=DesignSeeder # optional: top up to 100,000 designs
php scripts/assign-clothing-images.php # optional: use the 10 included clothing images
php artisan storage:link
npm run build
php artisan serve
```

On Windows PowerShell with script execution restrictions, use `npm.cmd`.
Sample local accounts: `admin@dorra.test` and `viewer@dorra.test`, password `password`.
Sample seeders only run in local/testing environments and preserve existing accounts.

## Browsing and managing designs

Use **Title starts with…** and Category, then **Apply filters**. Admins also have a Status filter; Viewers see Published designs only. Conditions
combine with AND. **Clear filters** resets the listing. Results use newest-first
`created_at DESC, id DESC` ordering and 24 rows per page; pagination preserves filters.
English example: `Modern` matches `Modern Business Card`, not `A Modern Card`.
Arabic example: `تصميم` matches `تصميم كارت شخصي`. Literal `%`, `_`, and `!` are
escaped. `utf8mb4_unicode_ci` defines comparisons; this is not fuzzy search, stemming,
or Arabic spelling normalization. Deep OFFSET pages and selective filters may need
further tuning. An external search service is not required for this implementation.

Admin forms require a title (maximum 255 characters), seeded category and status.
Required JPG/PNG/WebP thumbnails for new designs have a 2MB limit and use public storage with generated
names. New designs cannot be created without an image. Existing sample placeholders remain supported. Editing without an upload
keeps the image. Replaced/deleted uploads are removed; the shared placeholder is retained.
Deletion requires confirmation. Database constraints and schema are unchanged.

```sh
php artisan test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
php artisan dorra:verify-database
```

Tests use small isolated in-memory SQLite fixtures and fake file storage. Actual MySQL
schema/query plans are inspected separately. See [database setup](docs/database-setup.md),
[database verification](docs/database-verification.json), and
[library query plans](docs/library-query-plans.json). These are plans, not before/after
benchmark timings. Actual repeated measurements and million-row considerations are
in [the performance report](docs/performance.md); reproduce SQL measurements with
`php scripts/benchmark-database.php --runs=20 --warmup=3`. Repository delivery
and remote repository access are documented in [the delivery report](docs/reports/delivery.md). A clean source/dependency installation on an isolated MySQL database passed on the same Windows machine; see [clean-install evidence](docs/reports/clean-install.md). The original starter-kit
documentation is retained below.

# Laravel + Blade Starter Kit

---

## Introduction

Our Laravel 12 + Blade starter kit provides the typical functionality found in the Laravel Starter kits, but with a few key differences:

- A CoreUI/AdminLTE inspired design layout
- Blade + AlpineJS code

This kit aims to fill the gap where there is no simple **Blade only** starter kit available.

Our internal goal at Laravel Daily is to start using this starter kit for our Demo applications, to avoid overwhelming our audience with Vue/Livewire/React if we had used one of the official Laravel 12 starter kits.

**Note:** This is Work in Progress kit, so it will get updates and fixes/features as we go.

---

## Screenshots

![](https://laraveldaily.com/uploads/2025/05/LoginPage.png)

![](https://laraveldaily.com/uploads/2025/05/RegisterPage.png)

![](https://laraveldaily.com/uploads/2025/05/DashboardPage.png)

![](https://laraveldaily.com/uploads/2025/05/ProfilePage.png)

---

## What is Inside?

Inside you will find all the functions that you would expect:

- Authentication
    - Login
    - Registration
    - Password Reset Flow
    - Email Confirmation Flow
- Dashboard Page
- Profile Settings
    - Profile Information Page
    - Password Update Page
    - Appearance Preferences

---

## How to use it?

To use this kit, you can install it using:

```bash
laravel new --using=laraveldaily/starter-kit
```

From there, you can modify the kit to your needs.

---

## Design Elements

If you want to see examples of what design elements we have, you can [visit the Wiki](<https://github.com/LaravelDaily/starter-kit/wiki/Design-Examples-(Raw-Files)>) and see the raw HTML files.

---

## Licence

Starter kit is open-sourced software licensed under the MIT license.
