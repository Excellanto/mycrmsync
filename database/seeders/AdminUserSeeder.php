<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => null,
            ]
        );
        $superAdmin = Role::where('slug', 'super_admin')->where('guard_name', 'web')->firstOrFail();
        $user->syncRoles([$superAdmin]);
    }
}
