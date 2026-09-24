<?php

use App\Modules\Addresses\AddressesServiceProvider;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Categories\CategoriesServiceProvider;
use App\Modules\Products\ProductsServiceProvider;
use App\Modules\Users\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AddressesServiceProvider::class,
    UsersServiceProvider::class,
    CategoriesServiceProvider::class,
    ProductsServiceProvider::class,
    CartServiceProvider::class,
    AuthServiceProvider::class,
];
