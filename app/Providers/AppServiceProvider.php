<?php

namespace App\Providers;

use App\Models\DoanhNghiep;
use App\Observers\DoanhNghiepObserver;
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
        DoanhNghiep::observe(DoanhNghiepObserver::class);
    }
}
