<?php

declare(strict_types=1);

use Flick\Laravel\Adapters\LaravelSession;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

/**
 * LaravelSession::start().
 *
 * Laravel manages its own session lifecycle, so Flick's start() has to be a
 * no-op when the framework already started one — restarting would throw away
 * whatever the app had put there. That guard had never run.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/configuration.md,
 * "sessionAutoStart" — Flick defers to a framework that manages its own
 * session lifecycle.
 */
describe('LaravelSession::start()', function () {
    it('starts a session that has not been started', function () {
        $store = new Store('flick-test', new ArraySessionHandler(60));

        expect($store->isStarted())->toBeFalse();

        (new LaravelSession($store))->start();

        expect($store->isStarted())->toBeTrue();
    });

    it('leaves an already-started session alone', function () {
        // Laravel's Store survives a redundant start(), so asserting on stored
        // data would pass either way. The contract here is that Flick does not
        // call start() at all once the framework has.
        $session = Mockery::mock(Session::class);
        $session->shouldReceive('isStarted')->once()->andReturnTrue();
        $session->shouldNotReceive('start');

        (new LaravelSession($session))->start();

        expect(true)->toBeTrue();
    });

    it('calls start exactly once on a session that has not begun', function () {
        $session = Mockery::mock(Session::class);
        $session->shouldReceive('isStarted')->once()->andReturnFalse();
        $session->shouldReceive('start')->once();

        (new LaravelSession($session))->start();

        expect(true)->toBeTrue();
    });

    it('is safe to call twice', function () {
        $store = new Store('flick-test', new ArraySessionHandler(60));
        $session = new LaravelSession($store);

        $session->start();
        $session->setValue('user_id', 7);
        $session->start();

        expect($session->getValue('user_id'))->toBe(7);
    });
});
