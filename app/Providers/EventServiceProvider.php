<?php

namespace App\Providers;

use App\Events\LeadsListRequested;
use App\Events\CustomerListRequested;
use App\Events\QuotationCreated;
use App\Listeners\BuildLeadsQuery;
use App\Listeners\BuildCustomerQuery;
use App\Listeners\ProcessQuotationDuplication;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
          QuotationCreated::class => [
            ProcessQuotationDuplication::class,
        ],
    ];

    public function boot()
    {
        //
    }
}
