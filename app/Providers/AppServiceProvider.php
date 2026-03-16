<?php

namespace App\Providers;

use App\Models\HrisPersonalAccessToken;
use App\Services\DocumentCompressionService;
use App\Services\DynamicMailerService;
use App\Services\QuotationNotificationService;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DynamicMailerService::class, function ($app) {
            return new DynamicMailerService();
        });
        $this->app->singleton(DocumentCompressionService::class, function ($app) {
            return new DocumentCompressionService();
        });
        $this->app->bind(QuotationNotificationService::class, function ($app) {
            return new QuotationNotificationService(
                $app->make(DynamicMailerService::class)
            );
        });

        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(HrisPersonalAccessToken::class);
    }

}
