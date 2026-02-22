<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            return [
                Limit::perMinute(5)->by($request->ip() . '|' . $email),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
            ];
        });

        RateLimiter::for('attack-create', function (Request $request) {
            $userId = $request->user()?->id;
            $key = is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip();

            return [
                Limit::perMinute(30)->by($key . '|m'),
                Limit::perDay(100)->by($key . '|d'),
            ];
        });

        RateLimiter::for('attack-update', function (Request $request) {
            $userId = $request->user()?->id;
            $key = is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip();

            return [
                Limit::perMinute(30)->by($key . '|m'),
                Limit::perDay(200)->by($key . '|d'),
            ];
        });

        RateLimiter::for('attack-delete', function (Request $request) {
            $userId = $request->user()?->id;
            $key = is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip();

            return [
                Limit::perMinute(20)->by($key . '|m'),
                Limit::perDay(100)->by($key . '|d'),
            ];
        });

        RateLimiter::for('custom-trigger-create', function (Request $request) {
            $userId = $request->user()?->id;
            $key = is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip();

            return [
                Limit::perMinute(2)->by($key . '|m'),
                Limit::perDay(10)->by($key . '|d'),
            ];
        });

        RateLimiter::for('custom-option-create', function (Request $request) {
            $userId = $request->user()?->id;
            $key = is_int($userId) ? 'user:' . $userId : 'ip:' . $request->ip();

            return [
                Limit::perMinute(2)->by($key . '|m'),
                Limit::perDay(10)->by($key . '|d'),
            ];
        });
    }
}
