<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\SanctumServiceProvider::class,

    ...(env('TELESCOPE_ENABLED', false)
        ? [App\Providers\TelescopeServiceProvider::class]
        : []),
];