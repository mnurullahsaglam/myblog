<?php

declare(strict_types=1);

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AccessServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
