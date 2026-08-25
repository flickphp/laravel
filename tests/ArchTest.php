<?php

declare(strict_types=1);

test('no debugging statements in package code')
    ->arch()
    ->expect('Flick\\Laravel')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);

test('classes use strict types')
    ->arch()
    ->expect('Flick\\Laravel')
    ->toUseStrictTypes();

/*
| Containment rules. This package ships a second cookie writer and a second
| session implementation, both of which must delegate to Laravel rather than
| reach for the global functions themselves - and, being a library, it must
| never end the request.
|
| Namespace-scoped rather than flick's bare `expect('setcookie')->not->
| toBeUsed()` form: vendor/flickphp/flick is a symlink here too, so realpath()
| escapes Pest's vendor filter and the bare form would scan flick's source and
| fail on its NativeRequest.
*/
test('classes do not use exit or die')
    ->arch()
    ->expect('Flick\\Laravel')
    ->not->toUse(['exit', 'die']);

test('classes do not set cookies')
    ->arch()
    ->expect('Flick\\Laravel')
    ->not->toUse(['setcookie']);

test('classes do not drive the session directly')
    ->arch()
    ->expect('Flick\\Laravel')
    ->not->toUse(['session_start', 'session_regenerate_id', 'session_status', 'session_set_cookie_params']);

test('adapters implement interfaces')
    ->arch()
    ->expect('Flick\\Laravel\\Adapters\\LaravelRequest')
    ->toImplement('Flick\\Http\\RequestInterface');

test('session adapter implements interface')
    ->arch()
    ->expect('Flick\\Laravel\\Adapters\\LaravelSession')
    ->toImplement('Flick\\Session\\SessionInterface');

test('validation delegate implements interface')
    ->arch()
    ->expect('Flick\\Laravel\\Validation\\LaravelValidationDelegate')
    ->toImplement('Flick\\Validation\\ValidationDelegateInterface');
