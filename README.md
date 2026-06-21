# Laravel 11 + Vue 3 + InertiaJS Admin Boilerplate

Production-ready admin boilerplate featuring:
- Laravel 11 (PHP 8.2+), InertiaJS (Vue 3), Vite, TailwindCSS
- Authentication via Laravel Breeze (login, forgot/reset, email verification)
- Roles & Permissions via spatie/laravel-permission
- Admin middleware and access control
- RESTful controllers and policies
- Dashboard, Users, Roles, Permissions, Settings, and Language management modules

## Requirements
- PHP 8.2+
- Composer 2.x
- Node 18+
- Database (MySQL/PostgreSQL/SQLite)
- **Email ingestion (optional):** PHP `imap` extension for IMAP mailboxes (`webklex/laravel-imap`). Run the Laravel scheduler (`schedule:run` or `schedule:work`) and queue workers so `FetchEmailsJob` / `ProcessEmailJob` execute. After deploying schema changes, run `php artisan migrate` (includes `email_accounts.email_ingestion_enabled`) and rebuild frontend assets (`npm run build` or `npm run dev`) so the Email management table shows the **Ingestion** column and checkboxes.

## Installation
1. Create a new Laravel 11 project (or use this repo as the app root)
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. Configure your `.env` database connection.

3. Install frontend dependencies:
   ```bash
   npm install
   ```

4. Install Breeze (already required in composer.json) and ensure Inertia Vue stack:
   ```bash
   php artisan breeze:install vue
   npm run build
   ```
   Note: This repo already contains Inertia/Vue/Tailwind setup; the Breeze install ensures auth scaffolding and providers are registered.

5. Run migrations and seeders:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. (Optional) Sync language files into DB:
   ```bash
   php artisan lang:sync
   ```

7. Start the dev servers:
   ```bash
   php artisan serve
   npm run dev
   ```

8. Login with the seeded admin user:
   - Email: `admin@example.com`
   - Password: `password`

## Modules
### Authentication
- Login, Logout, Forgot Password, Reset Password, Email Verification
- Controllers in `app/Http/Controllers/Auth/*`
- Routes in `routes/auth.php`

### Users
- CRUD with role and permission assignment
- Controller: `app/Http/Controllers/UserController.php`
- Policy: `app/Policies/UserPolicy.php`
- Pages:
  - `resources/js/Pages/Admin/Users/Index.vue`
  - `resources/js/Pages/Admin/Users/Create.vue`
  - `resources/js/Pages/Admin/Users/Edit.vue`

### Roles & Permissions
- Spatie package integrated
- Controllers: `RoleController`, `PermissionController`
- Policies: `RolePolicy`, `PermissionPolicy`
- Pages:
  - `resources/js/Pages/Admin/Roles/Index.vue`
  - `resources/js/Pages/Admin/Roles/Edit.vue`
  - `resources/js/Pages/Admin/Permissions/Index.vue`
- Reusable component: `resources/js/Components/RolePermissionMatrix.vue`

### Site Settings
- DB table: `site_settings` with key/value/type
- Helper: `settings('key')`
- Seeder: `SettingSeeder`
- Controller: `SiteSettingController`
- Page: `resources/js/Pages/Admin/Settings/Index.vue`

### Multi-language (DB-based)
- DB table: `language_strings`
- Controller: `LanguageStringController` (index, update, syncLanguageFilesToDB)
- Middleware: `LoadTranslations` (shares DB translations to Inertia)
- Artisan command: `lang:sync`
- Page: `resources/js/Pages/Admin/Languages/Index.vue`

### Dashboard
- Controller: `DashboardController`
- Page: `resources/js/Pages/Admin/Dashboard.vue`
- Displays totals and last login activity

## Routing
- `routes/web.php` defines:
  - Guest routes from `routes/auth.php`
  - Authenticated user routes (dashboard)
  - Admin routes (`auth`, `verified`, `LoadTranslations`, `permission:admin-panel-access`, `tenant.active`)

## Middleware
- `permission:admin-panel-access` (Spatie) gates the admin UI; permissions are stored in the database.
- `LoadTranslations` shares DB translations to Inertia.

## Policies
- `UserPolicy`, `RolePolicy`, `PermissionPolicy`, `SettingPolicy`, `LanguageStringPolicy`
- `AuthServiceProvider` registers policies; route access uses `permission:admin-panel-access` and module checks use Spatie permissions / policies.

## Seeders
- `PermissionSeeder`, `RoleSeeder`, `AdminUserSeeder`, `SettingSeeder`
- Seeds default roles/permissions and an admin user (`admin@example.com` / `password`)

## Frontend
- Layout: `resources/js/Layouts/AdminLayout.vue`
- Components: `Table`, `Pagination`, `Modal`, `Badge`, `Toggle`, `RolePermissionMatrix`

## Notes
- After modifying `composer.json` autoload (helpers), run `composer dump-autoload`.
- Ensure your mail configuration is set for password reset and email verification.
- To change the initial admin credentials, update `AdminUserSeeder`.





