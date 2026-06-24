<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('html_body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('email_templates')->insert([
            [
                'slug' => 'login_otp',
                'name' => 'OTP Login',
                'subject' => 'Your login code',
                'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login code</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1f2937;">
	<p>Hello {{user_name}},</p>
	<p>Your one-time login code is:</p>
	<p style="font-size: 1.5rem; font-weight: 700; letter-spacing: 0.25em; font-family: ui-monospace, monospace;">{{otp_code}}</p>
	<p style="font-size: 0.875rem; color: #6b7280;">This code expires in {{expires_minutes}} minutes. If you did not request it, you can ignore this email.</p>
</body>
</html>
HTML,
                'variables' => json_encode(['otp_code', 'user_name', 'user_email', 'app_name', 'expires_minutes']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'password_recovery',
                'name' => 'Password Recovery',
                'subject' => 'Reset your password',
                'html_body' => <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Reset password</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1f2937;">
	<p>Hello {{user_name}},</p>
	<p>You are receiving this email because we received a password reset request for your account.</p>
	<p style="margin: 1.5rem 0;">
		<a href="{{reset_url}}" style="display: inline-block; padding: 0.625rem 1.25rem; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 0.375rem; font-weight: 600;">Reset password</a>
	</p>
	<p style="font-size: 0.875rem; color: #6b7280;">This link expires in {{expires_minutes}} minutes. If you did not request a password reset, you can ignore this email.</p>
	<p style="font-size: 0.75rem; color: #9ca3af; word-break: break-all;">{{reset_url}}</p>
</body>
</html>
HTML,
                'variables' => json_encode(['user_name', 'user_email', 'reset_url', 'app_name', 'expires_minutes']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Permission::firstOrCreate([
            'name' => 'nav.settings.email-templates.show',
            'guard_name' => 'web',
        ]);

        $permission = Permission::query()
            ->where('name', 'nav.settings.email-templates.show')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            Role::query()
                ->where(function ($query) {
                    $query->where('is_platform_scope', true)
                        ->orWhere('slug', 'super_admin');
                })
                ->each(function (Role $role) use ($permission): void {
                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                });
        }
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'nav.settings.email-templates.show')
            ->where('guard_name', 'web')
            ->delete();

        Schema::dropIfExists('email_templates');
    }
};
