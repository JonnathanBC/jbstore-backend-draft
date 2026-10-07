<?php

use App\Providers\AppServiceProvider;

use App\Modules\Addresses\AddressesServiceProvider;
use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Categories\CategoriesServiceProvider;
use App\Modules\Drivers\DriversServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Payments\PaymentsServiceProvider;
use App\Modules\Products\ProductsServiceProvider;
use App\Modules\Users\UsersServiceProvider;

return [
    AppServiceProvider::class,
    AddressesServiceProvider::class,
    UsersServiceProvider::class,
    CategoriesServiceProvider::class,
    ProductsServiceProvider::class,
    CartServiceProvider::class,
    AuthServiceProvider::class,
    PaymentsServiceProvider::class,
    OrdersServiceProvider::class,
    DriversServiceProvider::class,
];
