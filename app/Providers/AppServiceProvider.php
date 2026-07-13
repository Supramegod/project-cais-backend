<?php

namespace App\Providers;

use App\Models\HrisPersonalAccessToken;
use App\Models\SysmenuRole;
use App\Services\DocumentCompressionService;
use App\Services\DynamicMailerService;
use App\Services\Quotation\QuotationNotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->app->register('App\Providers\TelescopeServiceProvider');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(HrisPersonalAccessToken::class);
        if (str_contains(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    //     Gate::define('can-do', function ($user, $menuId, $permissionField) {
    //     // Cek apakah ada record di sysmenu_role untuk role_id user ini
    //     return SysmenuRole::where('role_id', $user->cais_role_id)
    //         ->where('sysmenu_id', $menuId)
    //         ->where($permissionField, true)
    //         ->exists();
    // });
    }

}
