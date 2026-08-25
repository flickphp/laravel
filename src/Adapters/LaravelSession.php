<?php

declare(strict_types=1);

namespace Flick\Laravel\Adapters;

use Closure;
use Flick\Session\SessionInterface;
use Illuminate\Contracts\Session\Session;

/**
 * Laravel adapter for Flick's SessionInterface.
 *
 * Wraps Laravel's session store to provide unified session access.
 * All Flick data is stored under the 'flick' namespace to avoid
 * conflicts with application session data.
 */
class LaravelSession implements SessionInterface
{
    /**
     * The namespace key for all Flick session data.
     */
    protected const NAMESPACE = 'flick';

    /**
     * A concrete session store, or a resolver closure that returns the current
     * store from the container. A resolver keeps the adapter correct when it
     * outlives a single request (e.g. long-lived workers under Octane), where a
     * store captured at boot time would otherwise go stale.
     *
     * @var Session|Closure
     */
    protected $session;

    public function __construct(Session|Closure $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->getSession()->isStarted();
    }

    /**
     * {@inheritdoc}
     *
     * Laravel manages sessions automatically, so this is typically a no-op.
     * We call start() for compatibility, but Laravel's middleware handles this.
     */
    public function start(): void
    {
        if (! $this->getSession()->isStarted()) {
            $this->getSession()->start();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateId(bool $deleteOldSession = false): void
    {
        $this->getSession()->regenerate($deleteOldSession);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(string $key, mixed $value): void
    {
        $data = $this->getSession()->get(self::NAMESPACE, []);
        $data[$key] = $value;
        $this->getSession()->put(self::NAMESPACE, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(string $key): mixed
    {
        $data = $this->getSession()->get(self::NAMESPACE, []);

        return $data[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasValue(string $key): bool
    {
        $data = $this->getSession()->get(self::NAMESPACE, []);

        // presence, not truthiness — see SessionInterface::hasValue()
        return is_array($data) && array_key_exists($key, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteValue(string $key): void
    {
        $data = $this->getSession()->get(self::NAMESPACE, []);
        unset($data[$key]);
        $this->getSession()->put(self::NAMESPACE, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(): void
    {
        $this->getSession()->forget(self::NAMESPACE);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->getSession()->get(self::NAMESPACE, []);
    }

    /**
     * Get the underlying Laravel session instance.
     *
     * When the adapter was constructed with a resolver closure, this pulls the
     * current store from the container on every call so a long-lived adapter
     * never serves a stale boot-time session.
     */
    public function getSession(): Session
    {
        return $this->session instanceof Closure
            ? ($this->session)()
            : $this->session;
    }
}
