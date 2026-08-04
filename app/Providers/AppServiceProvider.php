<?php

namespace App\Providers;

use App\Contracts\Http\ApiResponder;
use App\Http\Responses\DefaultApiResponder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiResponder::class, DefaultApiResponder::class);
    }

    public function boot(): void
    {
        RateLimiter::for('customer-avatar-upload', function (Request $request) {
            $customerId = $request->user('customer_api')?->getAuthIdentifier();

            return Limit::perMinute(20)->by('customer-avatar-upload:'.($customerId ?: $request->ip()));
        });
    }
}
