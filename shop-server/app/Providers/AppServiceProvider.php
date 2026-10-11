<?php

namespace App\Providers;

use App\Services\Settings\RuntimeConfigResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuntimeConfigResolver::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontend = rtrim((string) config('app.frontend_url'), '/');
            $email = urlencode((string) $notifiable->getEmailForPasswordReset());

            return "{$frontend}/reset-password?token={$token}&email={$email}";
        });

        try {
            $this->app->make(RuntimeConfigResolver::class)->apply();
        } catch (\Throwable) {
            // DB may be unavailable during early boot / package discovery.
        }
    }
}
