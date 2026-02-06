<?php

namespace App\Providers;

use App\Events\LeadsListRequested;
use App\Events\CustomerListRequested;
use App\Listeners\BuildLeadsQuery;
use App\Listeners\BuildCustomerQuery;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LeadsListRequested::class => [
            BuildLeadsQuery::class,
        ],
        CustomerListRequested::class => [
            BuildCustomerQuery::class,
        ],
    ];

    public function boot()
    {
        //
    }
}
