<?php

namespace App\Providers;

use App\Services\Wood\AiEvaluatorService;
use App\Services\Wood\CacheMatcherService;
use App\Services\Wood\FeatureExtractorService;
use App\Services\Wood\ImageDecoderService;
use App\Services\Wood\WoodBrainService;
use Illuminate\Support\ServiceProvider;

/**
 * Module 9 — Wood Service Provider
 *
 * Registers all Wood Anatomy services in the Laravel container.
 * WoodBrainService is a SINGLETON — one shared instance per request.
 * This mirrors the "Library" pattern from CodeIgniter that the team is familiar with.
 *
 * Sub-services are also singletons so they share connections/state
 * within a single request lifecycle.
 */
class WoodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sub-services (stateless — can be singletons safely)
        $this->app->singleton(ImageDecoderService::class);
        $this->app->singleton(FeatureExtractorService::class);
        $this->app->singleton(CacheMatcherService::class);
        $this->app->singleton(AiEvaluatorService::class);

        // The Brain — Singleton orchestrator
        // All dependencies are injected via the container
        $this->app->singleton(WoodBrainService::class, function ($app) {
            return new WoodBrainService(
                featureExtractor: $app->make(FeatureExtractorService::class),
                cacheMatcher:     $app->make(CacheMatcherService::class),
                aiEvaluator:      $app->make(AiEvaluatorService::class),
            );
        });

        // Convenient facade alias: app('wood.brain')
        $this->app->alias(WoodBrainService::class, 'wood.brain');
    }

    public function boot(): void
    {
        // Publish wood config
        $this->publishes([
            __DIR__ . '/../../config/wood.php' => config_path('wood.php'),
        ], 'wood-config');
    }
}
