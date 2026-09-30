<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@rockvanta.com']);
        $admin->name = 'Super Administrator';
        $admin->mobile = '9999999999';
        $admin->password = Hash::make('Admin@2026#CMS');
        $admin->role = UserRole::Admin;
        $admin->status = UserStatus::Active;
        $admin->is_super_admin = true;
        $admin->save();
    }
}
