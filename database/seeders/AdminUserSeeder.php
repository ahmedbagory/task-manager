<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = (string) config('task_manager.admin.name');
        $email = (string) config('task_manager.admin.email');
        $password = (string) config('task_manager.admin.password');

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([Rbac::SUPER_ADMIN]);
    }
}
