<?php

namespace App\Providers;

use App\Events\LeadsListRequested;
use App\Listeners\BuildLeadsQuery;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LeadsListRequested::class => [
            BuildLeadsQuery::class,
        ],
    ];

    public function boot()
    {
        //
    }
}
