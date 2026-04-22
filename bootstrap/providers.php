<?php

use App\Providers\AppServiceProvider;
use App\Providers\GenieAcsServiceProvider;
use App\Providers\HotspotServiceProvider;
use App\Providers\MikrotikServiceProvider;

return [
    AppServiceProvider::class,
    GenieAcsServiceProvider::class,
    HotspotServiceProvider::class,
    MikrotikServiceProvider::class,
];
