<?php

namespace App\Providers;

use App\Support\EdisiTerpilih;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instance per request (aman juga untuk Octane / test).
        $this->app->scoped(EdisiTerpilih::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
    }
}
