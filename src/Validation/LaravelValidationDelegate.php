<?php

declare(strict_types=1);

namespace Flick\Laravel\Validation;

use BadMethodCallException;
use Flick\Validation\ValidationDelegateInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as IlluminateValidator;

/**
 * Laravel validation delegate for Flick.
 *
 * Allows Flick forms to use Laravel validation rules (like `exists:users,id`
 * and `unique:posts,email`) seamlessly alongside native Flick rules.
 */
class LaravelValidationDelegate implements ValidationDelegateInterface
{
    /** @var array<string, mixed>|null Cache of Laravel's registered custom rule extensions. */
    private ?array $extensions = null;

    /**
     * Determine if this delegate can handle the given rule.
     *
     * Only claim rules Laravel's validator actually knows. Returning true for
     * everything breaks Flick's rule-string parser: it uses canHandle() to tell
     * a rule token from an argument, so an over-eager delegate makes each comma
     * argument (the "65" in between:18,65, the "id" in exists:users,id) look like
     * a separate rule, and comma-argument rules stop working entirely.
     */
    public function canHandle(string $rule): bool
    {
        $name = explode(':', $rule, 2)[0];

        // A bare argument fragment (e.g. "65", "blue") is never a rule name.
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            return false;
        }

        // Built-in Laravel rules: exists, unique, and every other validateX().
        if (method_exists(IlluminateValidator::class, 'validate'.Str::studly($name))) {
            return true;
        }

        // Custom rules registered via Validator::extend().
        return array_key_exists($name, $this->extensions());
    }

    /**
     * Laravel's registered custom validation extensions, resolved once.
     *
     * @return array<string, mixed>
     */
    private function extensions(): array
    {
        if ($this->extensions === null) {
            $this->extensions = Validator::make([], [])->extensions;
        }

        return $this->extensions;
    }

    /**
     * Validate a field value against a Laravel validation rule.
     *
     * @param  string  $field  The field name being validated
     * @param  mixed  $value  The field value to validate
     * @param  string  $rule  The validation rule to apply
     * @param  array  $allData  All form data (for cross-field validation)
     * @return array Array of error messages (empty array = validation passed)
     */
    public function validate(string $field, mixed $value, string $rule, array $allData = []): array
    {
        // Merge the current field value with all data
        $data = array_merge($allData, [$field => $value]);

        try {
            // Create a validator for this single rule
            $validator = Validator::make($data, [
                $field => $rule,
            ]);

            if ($validator->fails()) {
                return $validator->errors()->get($field);
            }
        } catch (BadMethodCallException $e) {
            // Laravel throws when the rule is unknown or misspelled. Surface it as
            // a form error instead of letting it 500 the request, preserving
            // Flick's graceful validation path.
            return ["The {$field} field uses an unknown validation rule."];
        }

        return [];
    }
}
