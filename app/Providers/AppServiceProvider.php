<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
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
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], absolute: false));

        Gate::define('manage-users', fn (User $user): bool => $user->role === 'super_admin');
        Gate::define('manage-portal', fn (User $user): bool => $user->is_admin === true);
    }
}
