<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_USER_NAME', 'Admin');
        $email = env('ADMIN_USER_EMAIL', 'admin@example.com');
        $password = env('ADMIN_USER_PASSWORD', 'admin12345');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),
                'is_admin' => true,
            ]
        );
    }
}
