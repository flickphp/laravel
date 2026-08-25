<?php

declare(strict_types=1);

use Flick\Laravel\Adapters\LaravelSession;
use Flick\Session\SessionInterface;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

describe('LaravelSession', function () {
    beforeEach(function () {
        // Create a real Laravel session store for testing
        $this->store = new Store('flick-test', new ArraySessionHandler(60));
        $this->store->start();
        $this->session = new LaravelSession($this->store);
    });

    it('implements SessionInterface', function () {
        expect($this->session)->toBeInstanceOf(SessionInterface::class);
    });

    it('reports active status correctly', function () {
        expect($this->session->isActive())->toBeTrue();
    });

    it('stores and retrieves values', function () {
        $this->session->setValue('user_id', 123);

        expect($this->session->getValue('user_id'))->toBe(123);
    });

    it('returns null for non-existent keys', function () {
        expect($this->session->getValue('nonexistent'))->toBeNull();
    });

    it('checks if values exist', function () {
        $this->session->setValue('exists', 'value');
        $this->session->setValue('empty', '');
        $this->session->setValue('zero', '0');

        expect($this->session->hasValue('exists'))->toBeTrue();
        expect($this->session->hasValue('nonexistent'))->toBeFalse();
        // presence, not truthiness — matches core's ArraySession/NativeSession
        expect($this->session->hasValue('empty'))->toBeTrue();
        expect($this->session->hasValue('zero'))->toBeTrue();
    });

    it('deletes values', function () {
        $this->session->setValue('key', 'value');
        $this->session->deleteValue('key');

        expect($this->session->getValue('key'))->toBeNull();
        expect($this->session->hasValue('key'))->toBeFalse();
    });

    it('destroys all flick data', function () {
        $this->session->setValue('key1', 'value1');
        $this->session->setValue('key2', 'value2');
        $this->session->destroy();

        expect($this->session->getAll())->toBe([]);
    });

    it('returns all stored values', function () {
        $this->session->setValue('a', 1);
        $this->session->setValue('b', 2);

        expect($this->session->getAll())->toBe(['a' => 1, 'b' => 2]);
    });

    it('namespaces data under flick key', function () {
        $this->session->setValue('test', 'value');

        // Verify the data is stored under the 'flick' namespace
        expect($this->store->get('flick'))->toBeArray();
        expect($this->store->get('flick')['test'])->toBe('value');
    });

    it('does not affect other session data', function () {
        // Store data outside Flick namespace
        $this->store->put('app_data', 'important');

        // Store Flick data
        $this->session->setValue('flick_key', 'flick_value');

        // Destroy Flick session
        $this->session->destroy();

        // App data should still exist
        expect($this->store->get('app_data'))->toBe('important');
    });

    it('exposes underlying session', function () {
        expect($this->session->getSession())->toBe($this->store);
    });

    it('stores complex values', function () {
        $data = [
            'user' => ['name' => 'John', 'email' => 'john@example.com'],
            'items' => [1, 2, 3],
        ];

        $this->session->setValue('complex', $data);

        expect($this->session->getValue('complex'))->toBe($data);
    });

    it('handles regenerate id', function () {
        // Just verify it doesn't throw
        $this->session->regenerateId();
        expect(true)->toBeTrue();

        $this->session->regenerateId(true);
        expect(true)->toBeTrue();
    });

    it('reflects the current session store when constructed with a resolver', function () {
        // Simulates Octane: the adapter is built once but must follow whatever
        // session store is currently bound, not the boot-time one.
        $first = new Store('flick-first', new ArraySessionHandler(60));
        $first->start();
        $this->app->instance('session.store', $first);

        $adapter = new LaravelSession(fn () => $this->app['session.store']);
        $adapter->setValue('k', 'first');

        expect($adapter->getValue('k'))->toBe('first');
        expect($adapter->getSession())->toBe($first);

        $second = new Store('flick-second', new ArraySessionHandler(60));
        $second->start();
        $this->app->instance('session.store', $second);

        // The rebound store is empty, then follows the adapter's writes.
        expect($adapter->getValue('k'))->toBeNull();
        $adapter->setValue('k', 'second');
        expect($adapter->getValue('k'))->toBe('second');
        expect($adapter->getSession())->toBe($second);
    });
});
