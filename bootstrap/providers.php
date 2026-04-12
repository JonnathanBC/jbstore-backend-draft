<?php

use App\Modules\Categories\CategoriesServiceProvider;
use App\Modules\Users\UsersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    UsersServiceProvider::class,
    CategoriesServiceProvider::class,
];
