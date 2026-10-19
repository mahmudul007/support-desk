<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\SendTicketNotifications;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent N+1 lazy loading queries in development and test environments
        Model::preventLazyLoading(! $this->app->isProduction());

        // Event subscribers
        Event::subscribe(SendTicketNotifications::class);
    }
}
