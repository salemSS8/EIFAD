<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Domain\Shared\Contracts\AIServiceInterface::class, function ($app) {
            $pipeline = config('ai.pipeline', []);
            $providers = [];

            foreach ($pipeline as $providerClass) {
                $providers[] = $app->make($providerClass);
            }

            return new \App\Domain\Shared\Services\AiServiceOrchestrator($providers);
        });

        $this->app->singleton(\App\Domain\Shared\Services\AiServiceOrchestrator::class, function ($app) {
            return $app->make(\App\Domain\Shared\Contracts\AIServiceInterface::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
