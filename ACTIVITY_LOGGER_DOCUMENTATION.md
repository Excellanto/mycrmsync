# Activity Logger Module - Documentation

## Overview

The Activity Logger Module is a comprehensive system that tracks and records every activity performed across the application. It provides role-based access control to ensure users only see logs for modules they have permission to access.

## Table of Contents

1. [Features](#features)
2. [Architecture](#architecture)
3. [Installation & Setup](#installation--setup)
4. [Usage Guide](#usage-guide)
5. [Permission-Based Access](#permission-based-access)
6. [API Reference](#api-reference)
7. [Frontend Components](#frontend-components)
8. [Customization](#customization)
9. [Best Practices](#best-practices)

---

## Features

- **Automatic Activity Logging**: Tracks CRUD operations on all registered models
- **Authentication Logging**: Records login, logout, and failed login attempts
- **Role-Based Visibility**: Users only see logs for modules they have access to
- **Advanced Filtering**: Search by user, module, action, date range, etc.
- **Export Functionality**: Export logs to CSV for reporting
- **Sensitive Data Masking**: Automatically masks passwords and sensitive fields
- **Performance Optimized**: Minimal overhead with indexed queries
- **Detailed Activity Properties**: Stores old/new values for audit trails

---

## Architecture

### Database Structure

**Table: `activity_logs`**

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | User who performed the action (nullable) |
| user_name | string | User's name (stored for deleted users) |
| module | string | Module name (e.g., 'users', 'roles') |
| action | string | Action type (e.g., 'created', 'updated') |
| description | string | Human-readable description |
| subject_type | string | Polymorphic model class |
| subject_id | bigint | Polymorphic model ID |
| properties | json | Old/new values and metadata |
| ip_address | string | User's IP address |
| user_agent | text | User's browser/device info |
| created_at | timestamp | When the action occurred |
| updated_at | timestamp | Record update time |

**Indexes:**
- `user_id` (foreign key)
- `module`
- `action`
- `created_at`
- Composite: `module + action`

### Core Components

1. **ActivityLog Model** (`app/Models/ActivityLog.php`)
   - Eloquent model with relationships
   - Query scopes for filtering
   - Permission-based access scope

2. **ActivityLogService** (`app/Services/ActivityLogService.php`)
   - Main logging service
   - Handles all log operations
   - Masks sensitive data

3. **Observers** (`app/Observers/`)
   - UserObserver
   - RoleObserver
   - PermissionObserver
   - SiteSettingObserver
   - LanguageStringObserver

4. **ActivityLogController** (`app/Http/Controllers/ActivityLogController.php`)
   - Handles API requests
   - Applies permission-based filtering
   - Export functionality

5. **ActivityLogPolicy** (`app/Policies/ActivityLogPolicy.php`)
   - Authorization logic
   - Module-level access control

6. **Authentication Listener** (`app/Listeners/LogAuthenticationEvents.php`)
   - Logs login/logout events
   - Failed login attempts

---

## Installation & Setup

### Step 1: Run Migrations

```bash
php artisan migrate
```

This will create the `activity_logs` table.

### Step 2: Seed Permissions

```bash
php artisan db:seed --class=PermissionSeeder
```

This adds the following permissions:
- `activity-logs.view`
- `activity-logs.export`
- `activity-logs.delete`

### Step 3: Assign Permissions

Assign the `activity-logs.view` permission to roles that should access the logger module:

```php
$role = Role::findByName('admin');
$role->givePermissionTo('activity-logs.view');
```

### Step 4: Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## Usage Guide

### Automatic Logging

All models registered with observers will automatically log CRUD operations:

```php
// Create a user - automatically logged
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Update - automatically logged
$user->update(['name' => 'Jane Doe']);

// Delete - automatically logged
$user->delete();
```

### Manual Logging

For custom actions, use the `ActivityLogService`:

```php
use App\Services\ActivityLogService;

class YourController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function customAction()
    {
        // Log a custom action
        $this->activityLogService->logCustom(
            module: 'payments',
            action: 'payment_processed',
            description: 'Payment of $100 processed successfully',
            properties: [
                'amount' => 100,
                'currency' => 'USD',
                'transaction_id' => 'TXN123456',
            ]
        );
    }
}
```

### Logging Methods

#### logCreated()
```php
$this->activityLogService->logCreated('moduleName', $model);
```

#### logUpdated()
```php
$this->activityLogService->logUpdated('moduleName', $model);
```

#### logDeleted()
```php
$this->activityLogService->logDeleted('moduleName', $model);
```

#### logCustom()
```php
$this->activityLogService->logCustom(
    module: 'orders',
    action: 'status_changed',
    description: 'Order status changed to shipped',
    properties: ['old_status' => 'pending', 'new_status' => 'shipped'],
    subject: $order
);
```

### Adding Logging to New Models

1. **Create an Observer** (optional, or use manual logging):

```php
<?php

namespace App\Observers;

use App\Models\YourModel;
use App\Services\ActivityLogService;

class YourModelObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function created(YourModel $model): void
    {
        $this->activityLogService->logCreated('your-module', $model);
    }

    public function updated(YourModel $model): void
    {
        if ($model->wasChanged()) {
            $this->activityLogService->logUpdated('your-module', $model);
        }
    }

    public function deleted(YourModel $model): void
    {
        $this->activityLogService->logDeleted('your-module', $model);
    }
}
```

2. **Register the Observer** in `AppServiceProvider`:

```php
use App\Models\YourModel;
use App\Observers\YourModelObserver;

public function boot(): void
{
    YourModel::observe(YourModelObserver::class);
}
```

3. **Add Permissions** to `PermissionSeeder`:

```php
$permissions = [
    // ... existing permissions
    'your-module.view',
    'your-module.create',
    'your-module.update',
    'your-module.delete',
];
```

---

## Permission-Based Access

### How It Works

The system uses a **module-based permission system**:

1. **Super Admin**: Sees ALL activity logs regardless of permissions
2. **Other Users**: Only see logs for modules they have permissions for

### Permission Naming Convention

Permissions follow the pattern: `module.action`

Examples:
- `users.view` → Can see logs for the 'users' module
- `roles.create` → Can see logs for the 'roles' module
- `settings.update` → Can see logs for the 'settings' module

### Example Scenarios

**Scenario 1: Limited Access User**
```php
User has permissions: ['patients.view', 'appointments.view']
Can see logs for: patients, appointments
Cannot see logs for: users, roles, permissions, settings
```

**Scenario 2: Manager with User Management**
```php
User has permissions: ['users.view', 'users.create', 'roles.view']
Can see logs for: users, roles
Cannot see logs for: permissions, settings, patients
```

**Scenario 3: Super Admin**
```php
User has role: 'Super Admin'
Can see logs for: EVERYTHING
```

### Implementation

The permission check is implemented in the `ActivityLog` model:

```php
// In ActivityLog.php
public function scopeAccessibleModules($query, User $user)
{
    if ($user->hasRole('Super Admin')) {
        return $query; // See everything
    }

    // Extract modules from permissions
    $permissions = $user->getAllPermissions()->pluck('name');
    $modules = $permissions->map(fn($p) => explode('.', $p)[0])->unique();

    return $query->whereIn('module', $modules);
}
```

---

## API Reference

### Endpoints

#### GET /admin/activity-logs
List all activity logs with filtering

**Query Parameters:**
- `search` - Search in description, action, module, user
- `module` - Filter by module name
- `action` - Filter by action type
- `user_id` - Filter by user ID
- `start_date` - Filter from date (YYYY-MM-DD)
- `end_date` - Filter to date (YYYY-MM-DD)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
      "module": "users",
      "action": "created",
      "description": "Created users: Jane Smith",
      "properties": { "new": { "name": "Jane Smith", ... } },
      "ip_address": "192.168.1.1",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "links": [...],
    "total": 100
  }
}
```

#### GET /admin/activity-logs/{id}
View single activity log details

#### GET /admin/activity-logs/export
Export logs to CSV (applies same filters as index)

---

## Frontend Components

### Activity Logs Page

Location: `resources/js/Pages/Admin/ActivityLogs/Index.vue`

**Features:**
- Paginated table display
- Advanced filtering
- Search functionality
- Export to CSV
- Modal for viewing details

**Usage:**
```vue
<Link :href="route('admin.activity-logs.index')">
  View Activity Logs
</Link>
```

---

## Customization

### Adding Sensitive Fields

To mask additional sensitive fields, update `ActivityLogService`:

```php
protected array $sensitiveFields = [
    'password',
    'password_confirmation',
    'token',
    'api_token',
    'credit_card',
    'cvv',
    'ssn', // Add your custom fields
    'tax_id',
];
```

### Custom Description Format

Override the `generateDescription()` method in `ActivityLogService`:

```php
protected function generateDescription(string $module, string $action, ?Model $subject): string
{
    // Your custom format
    return "Custom: {$action} on {$module}";
}
```

### Custom Badge Colors

Update badge colors in the Vue component:

```javascript
function getModuleBadgeColor(module) {
    const colors = {
        users: 'info',
        roles: 'warning',
        'your-module': 'success', // Add custom module colors
    };
    return colors[module] || 'secondary';
}
```

---

## Best Practices

### 1. Performance Optimization

**Batch Logging** for bulk operations:

```php
$activities = [];
foreach ($items as $item) {
    $activities[] = [
        'module' => 'items',
        'action' => 'imported',
        'description' => "Imported item: {$item->name}",
        'subject_type' => get_class($item),
        'subject_id' => $item->id,
        'properties' => ['item' => $item->toArray()],
    ];
}

$this->activityLogService->logBatch($activities);
```

### 2. Avoid Logging in Loops

**Bad:**
```php
foreach ($users as $user) {
    $user->update(['status' => 'active']);
    // Logs created inside loop - slow!
}
```

**Good:**
```php
User::whereIn('id', $userIds)->update(['status' => 'active']);
$this->activityLogService->logCustom('users', 'bulk_update', 'Updated 100 users to active');
```

### 3. Clean Up Old Logs

Create a scheduled job to archive/delete old logs:

```php
// In App\Console\Kernel
protected function schedule(Schedule $schedule)
{
    $schedule->command('activitylog:cleanup --days=90')->daily();
}
```

### 4. Meaningful Descriptions

Write clear, actionable descriptions:

**Bad:**
```php
$this->activityLogService->logCustom('users', 'action', 'Did something');
```

**Good:**
```php
$this->activityLogService->logCustom(
    'users',
    'password_reset',
    "Password reset requested for user: {$user->email}"
);
```

### 5. Security Considerations

- **Never log passwords in plain text** (already handled by sensitive field masking)
- **Limit log retention** based on compliance requirements
- **Restrict delete permissions** to prevent log tampering
- **Monitor for suspicious patterns** (e.g., multiple failed logins)

---

## Troubleshooting

### Logs not appearing?

1. Check observer registration in `AppServiceProvider`
2. Verify permissions are assigned correctly
3. Clear cache: `php artisan cache:clear`
4. Check error logs: `storage/logs/laravel.log`

### Permission denied errors?

1. Ensure user has `activity-logs.view` permission
2. Check user has access to the specific module
3. Verify policy is registered in `AuthServiceProvider`

### Performance issues?

1. Add database indexes if needed
2. Use batch logging for bulk operations
3. Implement log archiving/cleanup
4. Consider using queues for heavy logging

---

## Support & Contribution

For issues or questions about the Logger Module:

1. Check this documentation
2. Review code comments in source files
3. Contact the development team

---

## Changelog

### Version 1.0.0 (Initial Release)
- Basic activity logging
- CRUD operation tracking
- Authentication event logging
- Role-based access control
- Advanced filtering and search
- CSV export functionality
- Frontend UI with PrimeVue
- Comprehensive documentation

---

**Last Updated:** December 1, 2024
**Version:** 1.0.0

