<?php

declare(strict_types=1);

use Flick\Laravel\Adapters\LaravelRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;

/**
 * The parts of the Laravel request adapter that had never run: cookies, file
 * access, env lookups, and clear().
 *
 * A Laravel app gets these through the same RequestInterface a standalone app
 * uses, so a gap here means Flick behaves differently inside Laravel with
 * nothing to catch it.
 *
 * Spec: websites/flickphp.test/resources/docs/guide/adapters.md — the adapter
 * implements Flick's RequestInterface; these methods are that contract.
 */
describe('LaravelRequest cookies', function () {
    it('reads a cookie from the request', function () {
        $request = Request::create('/test', 'GET', [], ['flick_remember' => 'token-value']);
        $adapter = new LaravelRequest($request);

        expect($adapter->cookie('flick_remember'))->toBe('token-value');
    });

    it('falls back to the default for a cookie that is not there', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->cookie('nope', 'fallback'))->toBe('fallback');
    });

    it('reports a cookie that is present', function () {
        $request = Request::create('/test', 'GET', [], ['flick_remember' => 'token-value']);
        $adapter = new LaravelRequest($request);

        expect($adapter->hasCookie('flick_remember'))->toBeTrue();
    });

    it('reports a cookie that is absent', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->hasCookie('nope'))->toBeFalse();
    });

    it('queues a forget cookie when deleting', function () {
        // Laravel's request is read-only, so deletion is queued onto the
        // response rather than mutating the incoming request.
        $adapter = new LaravelRequest(Request::create('/test'));

        $adapter->deleteCookie('flick_remember');

        expect(Cookie::hasQueued('flick_remember'))->toBeTrue();
    });

    it('queues the deletion with an expiry in the past', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        $adapter->deleteCookie('flick_remember');

        expect(Cookie::queued('flick_remember')->getExpiresTime())->toBeLessThan(time());
    });
});

describe('LaravelRequest files', function () {
    it('returns an empty array when nothing was uploaded', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->files())->toBe([]);
    });

    it('returns every uploaded file keyed by field name', function () {
        $request = Request::create('/test', 'POST', [], [], [
            'avatar' => UploadedFile::fake()->create('avatar.png', 10),
            'resume' => UploadedFile::fake()->create('resume.pdf', 10),
        ]);

        $files = (new LaravelRequest($request))->files();

        expect($files)->toHaveKeys(['avatar', 'resume']);
    });

    it('describes an uploaded file in the shape flick expects', function () {
        $request = Request::create('/test', 'POST', [], [], [
            'avatar' => UploadedFile::fake()->create('avatar.png', 10),
        ]);

        $file = (new LaravelRequest($request))->file('avatar');

        // Flick's upload handlers read the same keys PHP puts in $_FILES.
        expect($file)->toBeArray()
            ->toHaveKeys(['name', 'type', 'tmp_name', 'error', 'size'])
            ->and($file['name'])->toBe('avatar.png');
    });

    it('returns null for a field with no upload', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->file('avatar'))->toBeNull();
    });
});

describe('LaravelRequest env', function () {
    it('reads an environment value', function () {
        putenv('FLICK_LARAVEL_TEST=from-env');

        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->env('FLICK_LARAVEL_TEST'))->toBe('from-env');

        putenv('FLICK_LARAVEL_TEST');
    });

    it('falls back to the default when the variable is unset', function () {
        $adapter = new LaravelRequest(Request::create('/test'));

        expect($adapter->env('FLICK_DEFINITELY_UNSET', 'fallback'))->toBe('fallback');
    });
});

describe('LaravelRequest clear', function () {
    it('empties the post data', function () {
        $request = Request::create('/test', 'POST', ['name' => 'Ada']);
        $adapter = new LaravelRequest($request);

        $adapter->clear();

        expect($adapter->postAll())->toBe([]);
    });

    it('empties the query data', function () {
        $request = Request::create('/test?page=2');
        $adapter = new LaravelRequest($request);

        $adapter->clear();

        expect($adapter->queryAll())->toBe([]);
    });

    it('empties both bags on a POST carrying a query string', function () {
        // Laravel's replace() only touches the input source for the current
        // method, so on a POST it clears the body and leaves the query string
        // behind. Clearing both is what makes clear() mean "clear".
        $request = Request::create('/test?page=2', 'POST', ['name' => 'Ada']);
        $adapter = new LaravelRequest($request);

        $adapter->clear();

        expect($adapter->postAll())->toBe([])
            ->and($adapter->queryAll())->toBe([]);
    });
});
