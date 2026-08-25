<?php

declare(strict_types=1);

namespace Flick\Laravel\Facades;

use Flick\Flick as FlickBase;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for creating Flick form instances.
 *
 * This facade provides optional static access to create Flick forms.
 * Each call to make() returns a new Flick instance configured
 * with Laravel's request and session adapters.
 *
 * @method static FlickBase make(array|string $config = [])
 *
 * @see FlickBase
 */
class Flick extends Facade
{
    /**
     * Create a new Flick instance with the given configuration.
     *
     * Unlike typical facades that resolve singletons, this always
     * creates a new instance since each form should be independent.
     */
    public static function make(array|string $config = []): FlickBase
    {
        // No merge here: the service provider already bridged config('flick')
        // into core's defaults at boot, and core merges those beneath the
        // instance config. The facade used to do its own live, shallow
        // array_merge on top — which stripped nothing (so a published
        // 'session' key was honored here and nowhere else), saw post-boot
        // config() mutation new Flick() never saw, and skipped the string
        // shorthand entirely (audit 2026-08-15, B25). One bridge, one
        // behavior: Flick::make($x) is exactly new Flick($x).
        return new FlickBase($config);
    }

    /**
     * Get the registered name of the component.
     *
     * Note: We don't actually bind to a key since we always create
     * new instances. This is required by the Facade base class.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flick';
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * Every static passthrough call would otherwise build a brand-new Flick, so
     * `Flick::addError()` then `Flick::getErrors()` would run on different
     * instances and silently lose all state. Forms must be independent, so there
     * is no correct shared instance to return — direct callers to make().
     */
    protected static function resolveFacadeInstance(mixed $name): mixed
    {
        throw new \BadMethodCallException(
            'The Flick facade only supports Flick::make(); call methods on the instance it returns.'
        );
    }
}
