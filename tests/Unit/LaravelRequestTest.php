<?php

declare(strict_types=1);

use Flick\Http\RequestInterface;
use Flick\Laravel\Adapters\LaravelRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;

describe('LaravelRequest', function () {
    it('implements RequestInterface', function () {
        $request = Request::create('/test');
        $adapter = new LaravelRequest($request);

        expect($adapter)->toBeInstanceOf(RequestInterface::class);
    });

    it('returns post data', function () {
        $request = Request::create('/test', 'POST', ['name' => 'John']);
        $adapter = new LaravelRequest($request);

        expect($adapter->post('name'))->toBe('John');
        expect($adapter->post('missing', 'default'))->toBe('default');
    });

    it('returns all post data', function () {
        $request = Request::create('/test', 'POST', ['name' => 'John', 'email' => 'john@example.com']);
        $adapter = new LaravelRequest($request);

        expect($adapter->postAll())->toBe(['name' => 'John', 'email' => 'john@example.com']);
    });

    it('checks if post key exists', function () {
        $request = Request::create('/test', 'POST', ['name' => 'John']);
        $adapter = new LaravelRequest($request);

        expect($adapter->hasPost('name'))->toBeTrue();
        expect($adapter->hasPost('missing'))->toBeFalse();
    });

    it('returns query data', function () {
        $request = Request::create('/test?page=1&sort=name');
        $adapter = new LaravelRequest($request);

        expect($adapter->query('page'))->toBe('1');
        expect($adapter->query('sort'))->toBe('name');
        expect($adapter->query('missing', 'default'))->toBe('default');
    });

    it('returns all query data', function () {
        $request = Request::create('/test?page=1&sort=name');
        $adapter = new LaravelRequest($request);

        expect($adapter->queryAll())->toBe(['page' => '1', 'sort' => 'name']);
    });

    it('checks if query key exists', function () {
        $request = Request::create('/test?page=1');
        $adapter = new LaravelRequest($request);

        expect($adapter->hasQuery('page'))->toBeTrue();
        expect($adapter->hasQuery('missing'))->toBeFalse();
    });

    it('returns input with post priority', function () {
        $request = Request::create('/test?name=Query', 'POST', ['name' => 'Post']);
        $adapter = new LaravelRequest($request);

        expect($adapter->input('name'))->toBe('Post');
    });

    it('falls back to query for input', function () {
        $request = Request::create('/test?page=1', 'POST', ['name' => 'John']);
        $adapter = new LaravelRequest($request);

        expect($adapter->input('page'))->toBe('1');
    });

    it('returns all merged input', function () {
        $request = Request::create('/test?page=1', 'POST', ['name' => 'John']);
        $adapter = new LaravelRequest($request);

        $all = $adapter->all();

        expect($all)->toHaveKey('name');
        expect($all)->toHaveKey('page');
        expect($all['name'])->toBe('John');
        expect($all['page'])->toBe('1');
    });

    it('checks if any input exists', function () {
        $request = Request::create('/test?page=1', 'POST', ['name' => 'John']);
        $adapter = new LaravelRequest($request);

        expect($adapter->has('name'))->toBeTrue();
        expect($adapter->has('page'))->toBeTrue();
        expect($adapter->has('missing'))->toBeFalse();
    });

    it('handles file uploads', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100);
        $request = Request::create('/test', 'POST', [], [], ['document' => $file]);
        $adapter = new LaravelRequest($request);

        expect($adapter->hasFile('document'))->toBeTrue();

        $fileData = $adapter->file('document');
        expect($fileData)->toBeArray();
        expect($fileData['name'])->toBe('document.pdf');
        expect($fileData)->toHaveKey('type');
        expect($fileData)->toHaveKey('tmp_name');
        expect($fileData)->toHaveKey('error');
        expect($fileData)->toHaveKey('size');
    });

    it('returns null for missing file', function () {
        $request = Request::create('/test', 'POST');
        $adapter = new LaravelRequest($request);

        expect($adapter->file('missing'))->toBeNull();
    });

    it('returns request method', function () {
        $request = Request::create('/test', 'POST');
        $adapter = new LaravelRequest($request);

        expect($adapter->method())->toBe('POST');
    });

    it('checks request method', function () {
        $request = Request::create('/test', 'POST');
        $adapter = new LaravelRequest($request);

        expect($adapter->isMethod('POST'))->toBeTrue();
        expect($adapter->isMethod('GET'))->toBeFalse();
    });

    it('detects ajax requests', function () {
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $adapter = new LaravelRequest($request);

        expect($adapter->isAjax())->toBeTrue();
    });

    it('returns server values', function () {
        $request = Request::create('/test');
        $adapter = new LaravelRequest($request);

        expect($adapter->server('REQUEST_METHOD'))->toBe('GET');
    });

    it('returns headers', function () {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $adapter = new LaravelRequest($request);

        expect($adapter->header('Accept'))->toBe('application/json');
    });

    it('returns request uri', function () {
        $request = Request::create('/test/path?query=1');
        $adapter = new LaravelRequest($request);

        expect($adapter->uri())->toBe('/test/path?query=1');
    });

    it('returns IP address', function () {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $adapter = new LaravelRequest($request);

        expect($adapter->ip())->toBe('192.168.1.1');
    });

    it('detects secure requests', function () {
        $request = Request::create('https://example.com/test');
        $adapter = new LaravelRequest($request);

        expect($adapter->isSecure())->toBeTrue();

        $insecure = Request::create('http://example.com/test');
        $insecureAdapter = new LaravelRequest($insecure);

        expect($insecureAdapter->isSecure())->toBeFalse();
    });

    it('exposes underlying request', function () {
        $request = Request::create('/test');
        $adapter = new LaravelRequest($request);

        expect($adapter->getRequest())->toBe($request);
    });

    it('reflects the current request when constructed with a resolver', function () {
        // Simulates Octane: the adapter is built once but must follow whatever
        // request is currently bound in the container, not the boot-time one.
        $first = Request::create('/first', 'POST', ['name' => 'first']);
        $this->app->instance('request', $first);

        $adapter = new LaravelRequest(fn () => $this->app['request']);

        expect($adapter->post('name'))->toBe('first');
        expect($adapter->getRequest())->toBe($first);

        $second = Request::create('/second', 'POST', ['name' => 'second']);
        $this->app->instance('request', $second);

        expect($adapter->post('name'))->toBe('second');
        expect($adapter->getRequest())->toBe($second);
    });

    it('preserves keys for keyed file fields', function () {
        // files[avatar] and files[banner] — string keys must survive conversion.
        $avatar = UploadedFile::fake()->image('avatar.jpg');
        $banner = UploadedFile::fake()->image('banner.jpg');

        $request = Request::create('/test', 'POST', [], [], [
            'files' => ['avatar' => $avatar, 'banner' => $banner],
        ]);
        $adapter = new LaravelRequest($request);

        $data = $adapter->file('files');

        expect($data['name'])->toHaveKeys(['avatar', 'banner']);
        expect($data['name']['avatar'])->toBe('avatar.jpg');
        expect($data['name']['banner'])->toBe('banner.jpg');
        expect($data['tmp_name']['avatar'])->toBeString();
    });

    it('handles deeply nested file fields without fataling', function () {
        // files[group][item] used to call getClientOriginalName() on an array.
        $file = UploadedFile::fake()->image('nested.jpg');

        $request = Request::create('/test', 'POST', [], [], [
            'files' => ['group' => ['item' => $file]],
        ]);
        $adapter = new LaravelRequest($request);

        $data = $adapter->file('files');

        expect($data['name']['group']['item'])->toBe('nested.jpg');
        expect($data['tmp_name']['group']['item'])->toBeString();
    });

    it('reports a deeply nested file field as present without fataling', function () {
        // files[group][item] — hasFile() flattened exactly one level, then called
        // getError() on what was still an array. file() already handled this shape.
        $file = UploadedFile::fake()->image('nested.jpg');

        $request = Request::create('/test', 'POST', [], [], [
            'files' => ['group' => ['item' => $file]],
        ]);
        $adapter = new LaravelRequest($request);

        expect($adapter->hasFile('files'))->toBeTrue();
    });

    it('reports a nested field holding only an empty slot as absent', function () {
        // The nested walk must still honour UPLOAD_ERR_NO_FILE at every depth.
        $empty = new UploadedFile(
            tempnam(sys_get_temp_dir(), 'flicknofile'),
            '',
            null,
            UPLOAD_ERR_NO_FILE,
            true,
        );

        $request = Request::create('/test', 'POST', [], [], [
            'files' => ['group' => ['item' => $empty]],
        ]);
        $adapter = new LaravelRequest($request);

        expect($adapter->hasFile('files'))->toBeFalse();
    });

    it('defaults cookies to the request scheme and Strict samesite', function () {
        $secureAdapter = new LaravelRequest(Request::create('https://example.com/test'));
        $secureAdapter->setCookie('flick_secure', 'value');

        $secure = collect(Cookie::getQueuedCookies())->first(fn ($c) => $c->getName() === 'flick_secure');

        expect($secure)->not->toBeNull();
        expect($secure->isSecure())->toBeTrue();
        expect($secure->getSameSite())->toBe('strict');

        $insecureAdapter = new LaravelRequest(Request::create('http://example.com/test'));
        $insecureAdapter->setCookie('flick_insecure', 'value');

        $insecure = collect(Cookie::getQueuedCookies())->first(fn ($c) => $c->getName() === 'flick_insecure');

        expect($insecure)->not->toBeNull();
        expect($insecure->isSecure())->toBeFalse();
    });
});
