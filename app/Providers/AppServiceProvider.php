<?php

namespace App\Providers;

use App\Strategies\Fee\FeeStrategyInterface;
use App\Strategies\Fee\TieredFeeStrategy;
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
        $this->app->bind(abstract: FeeStrategyInterface::class, concrete: TieredFeeStrategy::class);
    }
}
