<?php

declare(strict_types=1);

use Flick\Flick;
use Flick\Laravel\FlickServiceProvider;
use Illuminate\Http\Request;

describe('Laravel CSRF Token Integration', function () {
    beforeEach(function () {
        $this->app['session.store']->regenerateToken();
    });

    afterEach(function () {
        // Clean up after tests
        Flick::resetDefaultCsrfTokenGenerator();
    });

    it('registers Laravel csrf_token generator on boot', function () {
        // Reset first to ensure clean state
        Flick::resetDefaultCsrfTokenGenerator();

        // Re-boot the provider to set defaults fresh
        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $generator = Flick::getDefaultCsrfTokenGenerator();

        expect($generator)->not->toBeNull();
        expect($generator)->toBeInstanceOf(Closure::class);
    });

    it('generator returns Laravel csrf token', function () {
        // Reset and re-boot to ensure clean state
        Flick::resetDefaultCsrfTokenGenerator();

        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        $generator = Flick::getDefaultCsrfTokenGenerator();
        $token = $generator();

        // Laravel's csrf_token() returns the session token
        expect($token)->toBe(csrf_token());
    });

    it('form includes Laravel CSRF token when csrf config is false', function () {
        // Reset and re-boot to ensure clean state
        Flick::resetDefaultCsrfTokenGenerator();

        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        // Create a form with csrf disabled (uses the custom generator)
        // echo => false to return HTML instead of outputting it
        $form = new Flick(['csrf' => false, 'echo' => false]);
        $html = $form->open('/submit');

        // Should contain the token input
        expect($html)->toContain('name="_token"');
        // Should contain Laravel's token value
        expect($html)->toContain('value="'.csrf_token().'"');
    });

    it('allows a zero-config new Flick() POST to pass CSRF', function () {
        // Reset and re-boot so the Laravel CSRF generator is registered fresh.
        Flick::resetDefaultCsrfTokenGenerator();

        $provider = new FlickServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // A submitted Flick form. new Flick() never reads Laravel config, so its
        // csrf config is null; Laravel's middleware already validated the token,
        // so submitted() must NOT reject this as an expired session.
        $request = Request::create('/submit', 'POST', [
            '_id' => 'myForm',
            '_token' => csrf_token(),
            'name' => 'Jane',
        ]);
        $this->app->instance('request', $request);

        $form = new Flick(['echo' => false]);

        expect($form->submitted())->toBeTrue();
    });

    it('form uses Laravel token not Flick native token format', function () {
        // Reset and re-boot to ensure clean state
        Flick::resetDefaultCsrfTokenGenerator();

        $provider = new FlickServiceProvider($this->app);
        $provider->boot();

        // echo => false to return HTML instead of outputting it
        $form = new Flick(['csrf' => false, 'echo' => false]);
        $html = $form->open('/submit');

        $laravelToken = csrf_token();

        // Should have just the Laravel token, not the token|timestamp format
        expect($html)->toContain('value="'.$laravelToken.'"');
        // Flick's native format includes a pipe with timestamp - this should NOT be present
        expect($html)->not->toContain($laravelToken.'|');
    });
});
