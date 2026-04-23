<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\OrthancServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    OrthancServiceProvider::class,
];
