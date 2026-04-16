<?php

namespace App\Providers;

use App\Policies\AdminPolicy;
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
        //
    }

    protected function policies(): array
    {
        return [
            'App\Modules\Users\Models\User' => AdminPolicy::class,
        ];
    }
}
