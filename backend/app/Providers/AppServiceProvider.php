<?php

namespace App\Providers;

use App\Services\Payments\FakeMomo;
use App\Services\Payments\MomoGateway;
use App\Services\Payments\MtnMomo;
use App\Support\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
        $this->app->bind(MomoGateway::class, fn () => config('momo.driver') === 'mtn' ? new MtnMomo : new FakeMomo);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
