<?php

declare(strict_types=1);

use Flick\Flick as FlickBase;
use Flick\Laravel\Adapters\LaravelRequest;
use Flick\Laravel\Facades\Flick as FlickFacade;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

// Bug #15 — a failed upload must report like PHP's $_FILES: present, empty
// tmp_name, zero size — not hasFile()=false with tmp_name pointing at the CWD.
it('reports a failed upload consistently with the native adapter (#15)', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'flickup');
    file_put_contents($tmp, 'x');

    $file = new UploadedFile($tmp, 'big.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

    $request = Request::create('/upload', 'POST');
    $request->files->set('doc', $file);
    $adapter = new LaravelRequest($request);

    expect($adapter->hasFile('doc'))->toBeTrue();

    $data = $adapter->file('doc');
    expect($data['error'])->toBe(UPLOAD_ERR_INI_SIZE);
    expect($data['tmp_name'])->toBe('');
    expect($data['size'])->toBe(0);
    expect($data['type'])->toBe('');

    if (file_exists($tmp)) {
        unlink($tmp);
    }
});

// Bug #29 — hasPost()/input() must treat a null-valued POST key as present.
it('treats a null-valued POST key as present (#29 hasPost)', function () {
    $request = Request::create('/', 'POST');
    $request->request->set('optin', null);
    $adapter = new LaravelRequest($request);

    expect($adapter->hasPost('optin'))->toBeTrue();
    expect($adapter->hasPost('absent'))->toBeFalse();
});

it('lets a null POST value win over a GET value (#29 input)', function () {
    $request = Request::create('/?optin=from-query', 'GET');
    $request->setMethod('POST');
    $request->request->set('optin', null);
    $adapter = new LaravelRequest($request);

    expect($adapter->input('optin'))->toBeNull();
});

// Bug #29 — server('SCRIPT_NAME')/PHP_SELF must not include the query string.
it('does not leak the query string into SCRIPT_NAME/PHP_SELF (#29 server)', function () {
    $request = Request::create('/form.php?page=2&debug=1', 'GET');
    $adapter = new LaravelRequest($request);

    expect($adapter->server('SCRIPT_NAME'))->toBe('/form.php');
    expect($adapter->server('PHP_SELF'))->toBe('/form.php');
});

// Bug #29 — the facade only supports make(); stateless passthrough must throw.
it('supports Flick::make() but throws on stateless passthrough (#29 facade)', function () {
    expect(FlickFacade::make(['csrf' => false]))->toBeInstanceOf(FlickBase::class);

    expect(fn () => FlickFacade::getErrors())->toThrow(BadMethodCallException::class);
});
