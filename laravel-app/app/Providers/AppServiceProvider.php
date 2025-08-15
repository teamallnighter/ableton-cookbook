<?php

namespace App\Providers;

use App\Services\SeoService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SEO Service as singleton
        $this->app->singleton(SeoService::class, function ($app) {
            return new SeoService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Share SEO service with all views
        View::share('seoService', app(SeoService::class));
        
        // Add view composers for SEO data
        View::composer('*', function ($view) {
            // Add default structured data for website
            if (!$view->offsetExists('structuredData')) {
                $view->with('structuredData', app(SeoService::class)->getStructuredData('website'));
            }
        });
    }
}
