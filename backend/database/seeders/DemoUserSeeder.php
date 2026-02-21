<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('DEFAULT_USER_NAME', 'Demo User');
        $email = env('DEFAULT_USER_EMAIL', 'demo@example.com');
        $password = env('DEFAULT_USER_PASSWORD', 'password');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),
            ]
        );
    }
}
