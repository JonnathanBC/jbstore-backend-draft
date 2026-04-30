<?php

namespace App\Modules\Products;

use App\Modules\Products\Services\ProductsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductsService::class, function () {
            return new ProductsService();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Event::listen(
            \App\Modules\Categories\Events\SubcategoryDeleting::class,
            \App\Modules\Products\Listeners\CheckProductsBeforeSubcategoryDeletedListener::class
        );
    }
}
