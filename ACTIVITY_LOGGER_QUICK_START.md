# Activity Logger Module - Quick Start Guide

## ✅ Module Status: READY TO USE

The Activity Logger Module has been successfully installed and is ready to use!

---

## 🚀 Getting Started

### 1. Run Migrations & Seeders

```bash
# Run migrations to create the activity_logs table
php artisan migrate

# Seed permissions for activity logs
php artisan db:seed --class=PermissionSeeder

# Optional: Seed all data including test users
php artisan db:seed
```

### 2. Assign Permissions

Give users access to view activity logs:

```bash
php artisan tinker
```

```php
// Assign to a role
$role = Spatie\Permission\Models\Role::findByName('admin');
$role->givePermissionTo('activity-logs.view');

// Or assign to a specific user
$user = App\Models\User::find(1);
$user->givePermissionTo('activity-logs.view');
```

### 3. Access the Activity Logs

Navigate to: **http://your-app-url/admin/activity-logs**

Or click "Activity Logs" in the admin sidebar menu.

---

## 📝 What's Already Logging?

The following modules are **automatically logging** all CRUD operations:

✅ **Users** - Create, Update, Delete
✅ **Roles** - Create, Update, Delete
✅ **Permissions** - Create, Update, Delete
✅ **Settings** - Create, Update, Delete
✅ **Languages** - Create, Update, Delete
✅ **Authentication** - Login, Logout, Failed Logins

---

## 🔒 Permission-Based Access (CRITICAL FEATURE)

### How It Works

Users can **ONLY see logs for modules they have access to**.

**Example:**

```php
// User with only 'users.view' permission
Can see: Logs for 'users' module
Cannot see: Logs for 'roles', 'permissions', 'settings', etc.

// User with 'users.view' and 'roles.view' permissions
Can see: Logs for 'users' and 'roles' modules
Cannot see: Logs for 'permissions', 'settings', etc.

// Super Admin
Can see: ALL LOGS (no restrictions)
```

### Module-Permission Mapping

| Module Name | Required Permission |
|-------------|---------------------|
| users | users.* |
| roles | roles.* |
| permissions | permissions.* |
| settings | settings.* |
| languages | languages.* |
| auth | Any permission (login/logout) |

---

## 🆕 Adding Logging to a New Module

### Option 1: Using an Observer (Recommended for Models)

**Step 1:** Create an observer

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
        $this->activityLogService->logCreated('your-module-name', $model);
    }

    public function updated(YourModel $model): void
    {
        if ($model->wasChanged()) {
            $this->activityLogService->logUpdated('your-module-name', $model);
        }
    }

    public function deleted(YourModel $model): void
    {
        $this->activityLogService->logDeleted('your-module-name', $model);
    }
}
```

**Step 2:** Register the observer in `app/Providers/AppServiceProvider.php`

```php
use App\Models\YourModel;
use App\Observers\YourModelObserver;

public function boot(): void
{
    // ... existing observers
    YourModel::observe(YourModelObserver::class);
}
```

**Step 3:** Add permissions to `database/seeders/PermissionSeeder.php`

```php
$permissions = [
    // ... existing permissions
    'your-module-name.view',
    'your-module-name.create',
    'your-module-name.update',
    'your-module-name.delete',
];
```

**Step 4:** Run seeder and clear cache

```bash
php artisan db:seed --class=PermissionSeeder
php artisan cache:clear
```

---

### Option 2: Manual Logging (For Controllers)

**In your controller:**

```php
use App\Services\ActivityLogService;

class YourController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function store(Request $request)
    {
        $item = YourModel::create($request->validated());

        // Log the action
        $this->activityLogService->logCreated('your-module-name', $item);

        return redirect()->back();
    }

    public function customAction()
    {
        // Log a custom action
        $this->activityLogService->logCustom(
            module: 'your-module-name',
            action: 'custom_action_performed',
            description: 'A custom action was performed',
            properties: [
                'key1' => 'value1',
                'key2' => 'value2',
            ]
        );
    }
}
```

---

## 🎨 Available Logging Methods

```php
use App\Services\ActivityLogService;

// Inject the service
protected ActivityLogService $activityLogService;

// Log creation
$this->activityLogService->logCreated('module-name', $model);

// Log update
$this->activityLogService->logUpdated('module-name', $model);

// Log deletion
$this->activityLogService->logDeleted('module-name', $model);

// Log login
$this->activityLogService->logLogin($user);

// Log logout
$this->activityLogService->logLogout($user);

// Log failed login
$this->activityLogService->logFailedLogin($email);

// Log custom action
$this->activityLogService->logCustom(
    module: 'module-name',
    action: 'action-name',
    description: 'Human readable description',
    properties: ['key' => 'value'],
    subject: $model // optional
);

// Batch logging (for bulk operations)
$this->activityLogService->logBatch([
    [
        'module' => 'items',
        'action' => 'imported',
        'description' => 'Imported item 1',
        'subject_type' => 'App\\Models\\Item',
        'subject_id' => 1,
        'properties' => [],
    ],
    // ... more logs
]);
```

---

## 🔍 Viewing Activity Logs

### Frontend Interface

1. Navigate to `/admin/activity-logs`
2. Use filters:
   - **Search**: Search by description, user, action
   - **Module**: Filter by specific module
   - **Action**: Filter by action type (created, updated, deleted, etc.)
   - **User**: Filter by user who performed the action
   - **Date Range**: Filter by start and end date
3. Click the eye icon to view full details
4. Export to CSV for reporting

### Via Code

```php
use App\Models\ActivityLog;

// Get all logs
$logs = ActivityLog::latest()->paginate(15);

// Get logs for a specific module
$logs = ActivityLog::forModule('users')->get();

// Get logs for a specific user
$logs = ActivityLog::forUser(1)->get();

// Get logs with date range
$logs = ActivityLog::dateRange('2024-01-01', '2024-12-31')->get();

// Get logs the current user can access
$logs = ActivityLog::accessibleModules(auth()->user())->get();
```

---

## 🔐 Security Features

✅ **Sensitive Data Masking**: Passwords, tokens, credit cards automatically masked
✅ **Role-Based Access**: Users only see logs for modules they can access
✅ **Immutable Logs**: Logs cannot be modified after creation
✅ **IP Tracking**: Records IP address of all activities
✅ **User Agent Tracking**: Records browser/device information

### Sensitive Fields (Automatically Masked)

- password
- password_confirmation
- current_password
- new_password
- token
- api_token
- remember_token
- secret
- credit_card
- cvv

**To add more sensitive fields:**

Edit `app/Services/ActivityLogService.php`:

```php
protected array $sensitiveFields = [
    // ... existing fields
    'ssn',
    'tax_id',
    'bank_account',
];
```

---

## 📊 Available Permissions

Grant these permissions to users/roles as needed:

- **activity-logs.view** - View activity logs (respects module permissions)
- **activity-logs.export** - Export logs to CSV
- **activity-logs.delete** - Delete logs (Super Admin only recommended)

---

## 🎯 Testing the Module

### Test Automatic Logging

```bash
php artisan tinker
```

```php
// Create a user (should be logged automatically)
$user = App\Models\User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password'),
]);

// Update the user (should be logged)
$user->update(['name' => 'Updated Name']);

// Delete the user (should be logged)
$user->delete();

// Check the logs
App\Models\ActivityLog::latest()->take(3)->get();
```

### Test Permission-Based Access

```php
// Create a user with limited permissions
$user = App\Models\User::find(2);
$user->givePermissionTo('users.view');

// This user should only see 'users' module logs
App\Models\ActivityLog::accessibleModules($user)->pluck('module')->unique();
// Result: ["users"]
```

---

## 📚 Full Documentation

For complete documentation, see: **ACTIVITY_LOGGER_DOCUMENTATION.md**

---

## ✅ Checklist

- [x] Database migration created
- [x] ActivityLog model created
- [x] ActivityLogService created
- [x] Observers registered for all models
- [x] Authentication logging enabled
- [x] ActivityLogController with permission filtering
- [x] ActivityLogPolicy for authorization
- [x] Permissions seeded
- [x] Frontend UI created
- [x] Routes configured
- [x] Navigation updated
- [x] Documentation written

---

## 🐛 Troubleshooting

**Issue: Logs not appearing**
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Check observers are registered
# Look in: app/Providers/AppServiceProvider.php
```

**Issue: Permission denied**
```php
// Make sure user has the permission
$user->givePermissionTo('activity-logs.view');

// Make sure user has permission for the module
$user->givePermissionTo('users.view'); // to see users logs
```

**Issue: Old data not logging**
- Observers only work on new operations after registration
- Use manual logging for existing workflows

---

## 🎉 You're All Set!

The Activity Logger Module is now ready to track all activities in your application with role-based access control. Start using it by navigating to `/admin/activity-logs`!

For detailed examples and advanced features, check **ACTIVITY_LOGGER_DOCUMENTATION.md**.

