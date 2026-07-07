<?php

namespace App\Providers;

use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportFormat;
use App\Models\DoanhNghiepImportJob;
use App\Models\HanhChinhImportFormat;
use App\Models\HopTacXa;
use App\Models\HopTacXaImportFormat;
use App\Models\HopTacXaImportJob;
use App\Observers\DoanhNghiepObserver;
use Illuminate\Support\Facades\Route;
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

        Route::bind('hop_tac_xa', fn (string $value) => HopTacXa::query()->findOrFail($value));

        Route::bind('importJob', function (string $value, $route) {
            $uri = (string) $route->uri();

            if (str_contains($uri, 'hop-tac-xa')) {
                return HopTacXaImportJob::query()->findOrFail($value);
            }

            return DoanhNghiepImportJob::query()->findOrFail($value);
        });

        Route::bind('importFormat', function (string $value, $route) {
            $uri = (string) $route->uri();

            if (str_contains($uri, 'hanh-chinh')) {
                return HanhChinhImportFormat::query()->findOrFail($value);
            }

            if (str_contains($uri, 'hop-tac-xa')) {
                return HopTacXaImportFormat::query()->findOrFail($value);
            }

            return DoanhNghiepImportFormat::query()->findOrFail($value);
        });
    }
}
