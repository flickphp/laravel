<?php

declare(strict_types=1);

namespace Flick\Laravel;

use Flick\Flick;
use Flick\Laravel\Adapters\LaravelRequest;
use Flick\Laravel\Adapters\LaravelSession;
use Flick\Laravel\Validation\LaravelValidationDelegate;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for Flick form library.
 *
 * Automatically injects Laravel-specific request and session adapters
 * so that all Flick instances use Laravel's HTTP layer and session.
 */
class FlickServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/flick.php',
            'flick'
        );

        // Bind adapters as singletons. Pass resolver closures rather than the
        // boot-time request/session instances so the adapters always read the
        // current request/session from the container. This keeps them correct
        // when a single adapter outlives one request (e.g. under Octane) instead
        // of serving the first request's data to every later request.
        $this->app->singleton(LaravelRequest::class, function ($app) {
            return new LaravelRequest(fn () => $app['request']);
        });

        $this->app->singleton(LaravelSession::class, function ($app) {
            return new LaravelSession(fn () => $app['session.store']);
        });

        $this->app->singleton(LaravelValidationDelegate::class, function () {
            return new LaravelValidationDelegate;
        });
    }

    /**
     * Bootstrap the service provider.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/flick.php' => config_path('flick.php'),
        ], 'flick-config');

        // Set the default adapters for all Flick instances
        $this->setDefaultAdapters();

        // Bridge the published config('flick') into core so a bare new Flick()
        // honors the app's honeypot/views/echo/etc. settings. Drop the adapter
        // keys Laravel handles itself (request/session are wired above).
        $config = config('flick', []);
        unset($config['request'], $config['session']);
        Flick::setDefaultConfig($config);
    }

    /**
     * Set the default request and session adapters for Flick.
     *
     * This allows any `new Flick()` call to automatically use Laravel's
     * request and session without explicit configuration.
     */
    protected function setDefaultAdapters(): void
    {
        // Resolve adapters from the container
        $request = $this->app->make(LaravelRequest::class);
        $session = $this->app->make(LaravelSession::class);
        $validationDelegate = $this->app->make(LaravelValidationDelegate::class);

        // Set as defaults for all Flick instances
        Flick::setDefaultRequest($request);
        Flick::setDefaultSession($session);
        Flick::setDefaultValidationDelegate($validationDelegate);

        // Use Laravel's CSRF token instead of Flick's native CSRF
        Flick::setDefaultCsrfTokenGenerator(fn () => csrf_token());
    }
}
