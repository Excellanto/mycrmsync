<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * List of sensitive fields that should be masked in logs
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_token',
        'remember_token',
        'secret',
        'credit_card',
        'cvv',
    ];

    /**
     * Log an activity.
     */
    public function log(
        string $module,
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?string $description = null,
        ?User $user = null
    ): ?ActivityLog {
        try {
            $user = $user ?? Auth::user();

            // Mask sensitive data
            $properties = $this->maskSensitiveData($properties);

            $logData = [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'module' => $module,
                'action' => $action,
                'description' => $description ?? $this->generateDescription($module, $action, $subject),
                'properties' => $properties,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'subject_type' => null,
                'subject_id' => null,
            ];

            if ($subject !== null) {
                $logData['subject_type'] = get_class($subject);
                $logData['subject_id'] = $subject->getKey();
            }

            return ActivityLog::create($logData);
        } catch (\Exception $e) {
            // Log the error but don't break the application
            \Log::error('Failed to log activity: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Log a model creation.
     */
    public function logCreated(string $module, Model $model, array $additionalProperties = []): ?ActivityLog
    {
        $properties = array_merge([
            'new' => $this->getModelAttributes($model),
        ], $additionalProperties);

        return $this->log($module, 'created', $model, $properties);
    }

    /**
     * Log a model update.
     */
    public function logUpdated(string $module, Model $model, array $additionalProperties = []): ?ActivityLog
    {
        $changes = $model->getDirty();
        $original = [];

        foreach ($changes as $key => $value) {
            $original[$key] = $model->getOriginal($key);
        }

        $properties = array_merge([
            'old' => $original,
            'new' => $changes,
            'changes' => $changes,
        ], $additionalProperties);

        return $this->log($module, 'updated', $model, $properties);
    }

    /**
     * Log a model deletion.
     */
    public function logDeleted(string $module, Model $model, array $additionalProperties = []): ?ActivityLog
    {
        $properties = array_merge([
            'old' => $this->getModelAttributes($model),
        ], $additionalProperties);

        return $this->log($module, 'deleted', $model, $properties);
    }

    /**
     * Log a user login.
     */
    public function logLogin(User $user): ?ActivityLog
    {
        return $this->log('auth', 'login', $user, [], 'User logged in', $user);
    }

    /**
     * Log a user logout.
     */
    public function logLogout(User $user): ?ActivityLog
    {
        return $this->log('auth', 'logout', $user, [], 'User logged out', $user);
    }

    /**
     * Log a failed login attempt.
     */
    public function logFailedLogin(string $email): ?ActivityLog
    {
        return $this->log('auth', 'failed_login', null, ['email' => $email], 'Failed login attempt');
    }

    /**
     * Log a custom action.
     */
    public function logCustom(
        string $module,
        string $action,
        string $description,
        array $properties = [],
        ?Model $subject = null
    ): ?ActivityLog {
        return $this->log($module, $action, $subject, $properties, $description);
    }

    /**
     * Get model attributes for logging.
     */
    protected function getModelAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        return $this->maskSensitiveData($attributes);
    }

    /**
     * Mask sensitive data in the properties.
     */
    protected function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->sensitiveFields)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Generate a description for the activity.
     */
    protected function generateDescription(string $module, string $action, ?Model $subject): string
    {
        $subjectName = $subject ? $this->getSubjectName($subject) : '';

        return match ($action) {
            'created' => "Created {$module}: {$subjectName}",
            'updated' => "Updated {$module}: {$subjectName}",
            'deleted' => "Deleted {$module}: {$subjectName}",
            'restored' => "Restored {$module}: {$subjectName}",
            'viewed' => "Viewed {$module}: {$subjectName}",
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'failed_login' => 'Failed login attempt',
            default => ucfirst($action)." {$module}".($subjectName ? ": {$subjectName}" : ''),
        };
    }

    /**
     * Get the subject name for display.
     */
    protected function getSubjectName(Model $subject): string
    {
        // Try common name attributes
        foreach (['name', 'title', 'display_name', 'email'] as $attribute) {
            if (isset($subject->$attribute)) {
                return $subject->$attribute;
            }
        }

        // Fallback to ID
        return "#{$subject->getKey()}";
    }

    /**
     * Batch log multiple activities (useful for bulk operations).
     */
    public function logBatch(array $activities): void
    {
        try {
            $logs = [];
            $user = Auth::user();
            $ip = Request::ip();
            $userAgent = Request::userAgent();

            foreach ($activities as $activity) {
                $logs[] = [
                    'user_id' => $user?->id,
                    'user_name' => $user?->name,
                    'module' => $activity['module'],
                    'action' => $activity['action'],
                    'description' => $activity['description'] ?? null,
                    'subject_type' => $activity['subject_type'] ?? null,
                    'subject_id' => $activity['subject_id'] ?? null,
                    'properties' => json_encode($this->maskSensitiveData($activity['properties'] ?? [])),
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ActivityLog::insert($logs);
        } catch (\Exception $e) {
            \Log::error('Failed to batch log activities: '.$e->getMessage());
        }
    }
}
