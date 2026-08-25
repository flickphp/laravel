<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Laravel\Adapters\LaravelRequest;
use Flick\Laravel\Adapters\LaravelSession;
use Flick\Laravel\FlickServiceProvider;
use Flick\Laravel\Validation\LaravelValidationDelegate;

describe('FlickServiceProvider', function () {
    afterEach(function () {
        // Clean up after tests that modify defaults
        Flick::resetDefaultRequest();
        Flick::resetDefaultSession();
        Flick::resetDefaultValidationDelegate();
        Flick::resetDefaultCsrfTokenGenerator();
        Flick::resetDefaultConfig();
    });

    it('is registered as a provider', function () {
        expect($this->app->getProviders(FlickServiceProvider::class))->not->toBeEmpty();
    });

    it('registers LaravelRequest as singleton', function () {
        $adapter1 = $this->app->make(LaravelRequest::class);
        $adapter2 = $this->app->make(LaravelRequest::class);

        expect($adapter1)->toBeInstanceOf(LaravelRequest::class);
        expect($adapter1)->toBe($adapter2);
    });

    it('registers LaravelSession as singleton', function () {
        $adapter1 = $this->app->make(LaravelSession::class);
        $adapter2 = $this->app->make(LaravelSession::class);

        expect($adapter1)->toBeInstanceOf(LaravelSession::class);
        expect($adapter1)->toBe($adapter2);
    });

    it('registers LaravelValidationDelegate as singleton', function () {
        $delegate1 = $this->app->make(LaravelValidationDelegate::class);
        $delegate2 = $this->app->make(LaravelValidationDelegate::class);

        expect($delegate1)->toBeInstanceOf(LaravelValidationDelegate::class);
        expect($delegate1)->toBe($delegate2);
    });

    it('sets default request adapter on boot', function () {
        // Re-boot the provider to set defaults fresh
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $default = Flick::getDefaultRequest();

        expect($default)->toBeInstanceOf(LaravelRequest::class);
    });

    it('sets default session adapter on boot', function () {
        // Re-boot the provider to set defaults fresh
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $default = Flick::getDefaultSession();

        expect($default)->toBeInstanceOf(LaravelSession::class);
    });

    it('sets default validation delegate on boot', function () {
        // Re-boot the provider to set defaults fresh
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $default = Flick::getDefaultValidationDelegate();

        expect($default)->toBeInstanceOf(LaravelValidationDelegate::class);
    });

    it('sets default CSRF token generator on boot', function () {
        // Reset first to ensure clean state
        Flick::resetDefaultCsrfTokenGenerator();

        // Re-boot the provider to set defaults fresh
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $generator = Flick::getDefaultCsrfTokenGenerator();

        expect($generator)->not->toBeNull();
        expect($generator)->toBeInstanceOf(Closure::class);
    });

    it('merges default config', function () {
        expect(config('flick'))->toBeArray();
        expect(config('flick.views'))->toBe('tailwind');
        expect(config('flick.csrf'))->toBeFalse();
        expect(config('flick.sessionAutoStart'))->toBeFalse();
    });

    it('allows config override', function () {
        config(['flick.views' => 'bootstrap']);

        expect(config('flick.views'))->toBe('bootstrap');
    });

    it('bridges published config into core defaults on boot', function () {
        config(['flick.id' => 'publishedForm', 'flick.honeypot' => 'website']);

        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        // A bare new Flick() now honors the published config.
        expect(Flick::getDefaultConfig()['id'])->toBe('publishedForm');
        expect(Flick::getDefaultConfig()['honeypot'])->toBe('website');

        $form = new Flick;
        expect($form->config('id'))->toBe('publishedForm');
    });

    it('does not bridge adapter keys that Laravel manages', function () {
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $defaults = Flick::getDefaultConfig();
        expect($defaults)->not->toHaveKey('request');
        expect($defaults)->not->toHaveKey('session');
    });
});
