<?php

declare(strict_types=1);

use Flick\Flick as FlickBase;
use Flick\Laravel\Facades\Flick as FlickFacade;
use Flick\Laravel\FlickServiceProvider;

/*
 * Audit 2026-08-15, B25 — config('flick') was bridged into instances twice,
 * by two mechanisms that disagreed: the provider snapshots at boot and strips
 * the adapter keys Laravel manages; the facade's make() did its own live,
 * shallow array_merge that stripped nothing. So a published 'session' key was
 * honored via Flick::make() but not via new Flick(), post-boot config()
 * mutation was visible on one path only, and make('bootstrap') took a third
 * code path. make() now defers to the same provider bridge new Flick() uses:
 * one mechanism, identical visibility.
 */

describe('Facade make() config parity', function () {
    afterEach(function () {
        FlickBase::resetDefaultRequest();
        FlickBase::resetDefaultSession();
        FlickBase::resetDefaultValidationDelegate();
        FlickBase::resetDefaultCsrfTokenGenerator();
        FlickBase::resetDefaultConfig();
    });

    it('sees post-boot config mutation exactly like new Flick() does: not at all', function () {
        (new FlickServiceProvider($this->app))->boot();

        config(['flick.honeypot' => 'late_trap']);

        // both paths keep the boot-time snapshot; neither sees the mutation
        expect(FlickFacade::make()->config('honeypot'))
            ->toBe((new FlickBase)->config('honeypot'))
            ->not->toBe('late_trap');
    });

    it('honors boot-time published config through the same bridge as new Flick()', function () {
        config(['flick.honeypot' => 'website']);
        (new FlickServiceProvider($this->app))->boot();

        expect(FlickFacade::make()->config('honeypot'))->toBe('website')
            ->and(FlickFacade::make(['honeypot' => 'override'])->config('honeypot'))->toBe('override');
    });

    it('treats the string shorthand the way new Flick() does', function () {
        (new FlickServiceProvider($this->app))->boot();

        expect(FlickFacade::make('bootstrap')->config('views'))
            ->toBe((new FlickBase('bootstrap'))->config('views'));
    });
});
