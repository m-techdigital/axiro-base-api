<?php

namespace App\Providers;

use App\Contracts\Http\ApiResponder;
use App\Http\Responses\DefaultApiResponder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiResponder::class, DefaultApiResponder::class);
    }

    public function boot(): void {}
}
