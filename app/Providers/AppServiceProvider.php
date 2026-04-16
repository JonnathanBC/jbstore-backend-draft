<?php

namespace App\Providers;

use App\Policies\AdminPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin', [AdminPolicy::class, 'admin']);
    }
}
