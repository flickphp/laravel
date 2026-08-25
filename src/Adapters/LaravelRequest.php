<?php

declare(strict_types=1);

namespace Flick\Laravel\Adapters;

use Closure;
use Flick\Http\RequestInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;

/**
 * Laravel adapter for Flick's RequestInterface.
 *
 * Wraps Illuminate\Http\Request to provide unified access to POST, GET,
 * FILES, cookies, headers, and server data in Laravel applications.
 */
class LaravelRequest implements RequestInterface
{
    /**
     * A concrete request instance, or a resolver closure that returns the
     * current request from the container. A resolver keeps the adapter correct
     * when it outlives a single request (e.g. long-lived workers under Octane),
     * where a request captured at boot time would otherwise go stale.
     *
     * @var Request|Closure
     */
    protected $request;

    public function __construct(Request|Closure $request)
    {
        $this->request = $request;
    }

    // POST data ---------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->getRequest()->post($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function postAll(): array
    {
        return $this->getRequest()->post() ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasPost(string $key): bool
    {
        // Key existence, not non-null value: a key present with a null value
        // (e.g. injected by middleware merge()) must count as present, matching
        // NativeRequest's array_key_exists semantics.
        return $this->getRequest()->request->has($key);
    }

    // GET/query data ----------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->getRequest()->query($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function queryAll(): array
    {
        return $this->getRequest()->query() ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasQuery(string $key): bool
    {
        return $this->getRequest()->query($key) !== null;
    }

    // Combined input (POST priority) ------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // POST takes priority over GET. Decide on key presence, not a non-null
        // value, so a POSTed key with a null value still wins over a GET value.
        if ($this->getRequest()->request->has($key)) {
            return $this->getRequest()->request->get($key);
        }

        return $this->getRequest()->query($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        // Merge GET over POST so POST takes priority for duplicate keys
        return array_merge($this->queryAll(), $this->postAll());
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->hasPost($key) || $this->hasQuery($key);
    }

    // Files -------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function file(string $key): ?array
    {
        $file = $this->getRequest()->file($key);

        if ($file === null) {
            return null;
        }

        // Convert UploadedFile(s) to a $_FILES-style array for Flick compatibility.
        // Array inputs (files[], files[a][b], files[avatar]) preserve their keys and
        // nesting across every $_FILES sub-key, mirroring native PHP's structure.
        if (is_array($file)) {
            return [
                'name' => $this->mapFileProperty($file, 'name'),
                'type' => $this->mapFileProperty($file, 'type'),
                'tmp_name' => $this->mapFileProperty($file, 'tmp_name'),
                'error' => $this->mapFileProperty($file, 'error'),
                'size' => $this->mapFileProperty($file, 'size'),
            ];
        }

        // Single file
        return $this->fileToArray($file);
    }

    /**
     * Recursively extract a single $_FILES property (name/type/tmp_name/error/size)
     * from a nested array of UploadedFile instances, preserving the original keys.
     *
     * @param  array<array-key, mixed>  $files
     * @return array<array-key, mixed>
     */
    private function mapFileProperty(array $files, string $property): array
    {
        $result = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $result[$key] = $this->mapFileProperty($file, $property);
            } elseif ($file instanceof UploadedFile) {
                $result[$key] = $this->fileProperty($file, $property);
            }
            // Anything that is neither an array nor an UploadedFile is skipped,
            // guarding against calling file methods on unexpected values.
        }

        return $result;
    }

    /**
     * Build a flat $_FILES-style array for a single uploaded file.
     *
     * @return array{name: string, type: string, tmp_name: string|false, error: int, size: int|false}
     */
    private function fileToArray(UploadedFile $file): array
    {
        return [
            'name' => $this->fileProperty($file, 'name'),
            'type' => $this->fileProperty($file, 'type'),
            'tmp_name' => $this->fileProperty($file, 'tmp_name'),
            'error' => $this->fileProperty($file, 'error'),
            'size' => $this->fileProperty($file, 'size'),
        ];
    }

    /**
     * Read a single $_FILES property from an uploaded file.
     */
    private function fileProperty(UploadedFile $file, string $property): mixed
    {
        // For a failed upload the temp path is empty; getRealPath('') resolves to
        // the process CWD (a real server directory) and getSize() returns false.
        // Report the same shape PHP's $_FILES gives on failure so downstream
        // "file too large" handling isn't skipped.
        $ok = $file->getError() === UPLOAD_ERR_OK;

        return match ($property) {
            'name' => $file->getClientOriginalName(),
            'type' => $ok ? $file->getClientMimeType() : '',
            'tmp_name' => $ok ? $file->getPathname() : '',
            'error' => $file->getError(),
            'size' => $ok ? $file->getSize() : 0,
            default => null,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function files(): array
    {
        $files = $this->getRequest()->allFiles();
        $result = [];

        foreach ($files as $key => $file) {
            $result[$key] = $this->file($key);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function hasFile(string $key): bool
    {
        // Mirror NativeRequest: a file is "present" whenever it exists with any
        // error other than UPLOAD_ERR_NO_FILE. Laravel's hasFile() additionally
        // requires a valid, moved file, so it reports false for a failed upload
        // (e.g. too large) and the error path never runs.
        return $this->hasUploadedFile($this->getRequest()->file($key));
    }

    /**
     * Look for one real upload anywhere in an uploaded-file value.
     *
     * Array inputs nest to any depth (files[], files[a][b]), so this recurses
     * the same way file() does. Anything that is neither an array nor an
     * UploadedFile counts as absent, matching Laravel's own instanceof guard.
     */
    private function hasUploadedFile(mixed $file): bool
    {
        if (is_array($file)) {
            foreach ($file as $nested) {
                if ($this->hasUploadedFile($nested)) {
                    return true;
                }
            }

            return false;
        }

        return $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    // Server/environment ------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function server(string $key, mixed $default = null): mixed
    {
        if ($key === 'SCRIPT_NAME' || $key === 'PHP_SELF') {
            // Return the current path only. getRequestUri() includes the query
            // string, which would otherwise leak into a form action (Build::open)
            // and into redirect('self').
            return strtok($this->getRequest()->getRequestUri(), '?');
        }

        return $this->getRequest()->server($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function method(): string
    {
        return $this->getRequest()->method();
    }

    /**
     * {@inheritdoc}
     */
    public function isMethod(string $method): bool
    {
        return $this->getRequest()->isMethod($method);
    }

    /**
     * {@inheritdoc}
     */
    public function isAjax(): bool
    {
        return $this->getRequest()->ajax();
    }

    // Cookies & headers -------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->getRequest()->cookie($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCookie(string $key): bool
    {
        return $this->getRequest()->cookie($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCookie(string $key): void
    {
        // Queue cookie deletion via Laravel's Cookie facade
        Cookie::queue(Cookie::forget($key));
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie(string $name, string $value, array $options = []): void
    {
        $expires = $options['expires'] ?? 0;
        $path = $options['path'] ?? '/';
        $domain = $options['domain'] ?? null;
        // Default to the request's scheme and the stricter SameSite policy, matching
        // Flick's native cookie adapter (isSecure() + 'Strict'). Explicit options win.
        $secure = $options['secure'] ?? $this->getRequest()->secure();
        $httpOnly = $options['httponly'] ?? true;
        $sameSite = $options['samesite'] ?? 'Strict';

        // Convert timestamp to minutes from now
        $minutes = 0;
        if ($expires > 0) {
            $minutes = (int) ceil(($expires - time()) / 60);
        }

        // Queue the cookie via Laravel's Cookie facade
        Cookie::queue($name, $value, $minutes, $path, $domain, $secure, $httpOnly, false, $sameSite);
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->getRequest()->header($key, $default);
    }

    // Environment -------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function env(string $key, mixed $default = null): mixed
    {
        // Use Laravel's env() helper for consistent environment access
        return env($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function ip(): string
    {
        return $this->getRequest()->ip() ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure(): bool
    {
        return $this->getRequest()->secure();
    }

    // Utility -----------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function uri(): string
    {
        return $this->getRequest()->getRequestUri();
    }

    /**
     * {@inheritdoc}
     *
     * Note: In Laravel, request data is typically immutable. This method
     * creates a new request instance without the current POST/GET data.
     */
    public function clear(): void
    {
        // Laravel's request is immutable, so we replace the underlying data
        $this->getRequest()->replace([]);
        $this->getRequest()->query->replace([]);
    }

    /**
     * Get the underlying Laravel request instance.
     *
     * When the adapter was constructed with a resolver closure, this pulls the
     * current request from the container on every call so a long-lived adapter
     * never serves stale boot-time data.
     */
    public function getRequest(): Request
    {
        return $this->request instanceof Closure
            ? ($this->request)()
            : $this->request;
    }
}
