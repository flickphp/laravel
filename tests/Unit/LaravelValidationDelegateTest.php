<?php

declare(strict_types=1);

use Flick\Laravel\Validation\LaravelValidationDelegate;
use Flick\Validation\ValidationDelegateInterface;

describe('LaravelValidationDelegate', function () {
    it('implements ValidationDelegateInterface', function () {
        $delegate = new LaravelValidationDelegate;

        expect($delegate)->toBeInstanceOf(ValidationDelegateInterface::class);
    });

    it('canHandle returns true only for real Laravel rules, not argument fragments', function () {
        $delegate = new LaravelValidationDelegate;

        // Real Laravel rules are claimed for delegation.
        expect($delegate->canHandle('exists:users,id'))->toBeTrue();
        expect($delegate->canHandle('unique:posts,email'))->toBeTrue();
        expect($delegate->canHandle('required'))->toBeTrue();
        expect($delegate->canHandle('email'))->toBeTrue();

        // Comma-argument fragments and unknown names are NOT rules, so the core
        // parser can absorb them as arguments instead of splitting them off.
        expect($delegate->canHandle('65'))->toBeFalse();
        expect($delegate->canHandle('blue'))->toBeFalse();
        expect($delegate->canHandle('id'))->toBeFalse();
        expect($delegate->canHandle('some_random_rule'))->toBeFalse();
    });

    it('returns empty array when validation passes', function () {
        $delegate = new LaravelValidationDelegate;

        $errors = $delegate->validate('email', 'test@example.com', 'email');

        expect($errors)->toBeArray()->toBeEmpty();
    });

    it('returns error messages when validation fails', function () {
        $delegate = new LaravelValidationDelegate;

        $errors = $delegate->validate('email', 'not-an-email', 'email');

        expect($errors)->toBeArray()->not->toBeEmpty();
        expect($errors[0])->toContain('email');
    });

    it('validates required rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Empty value fails required
        $errors = $delegate->validate('name', '', 'required');
        expect($errors)->not->toBeEmpty();

        // Value passes required
        $errors = $delegate->validate('name', 'John', 'required');
        expect($errors)->toBeEmpty();
    });

    it('validates min rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Too short
        $errors = $delegate->validate('password', 'abc', 'min:8');
        expect($errors)->not->toBeEmpty();

        // Long enough
        $errors = $delegate->validate('password', 'password123', 'min:8');
        expect($errors)->toBeEmpty();
    });

    it('validates max rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Too long
        $errors = $delegate->validate('name', 'This is a very long name', 'max:10');
        expect($errors)->not->toBeEmpty();

        // Short enough
        $errors = $delegate->validate('name', 'John', 'max:10');
        expect($errors)->toBeEmpty();
    });

    it('validates in rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Not in list
        $errors = $delegate->validate('status', 'unknown', 'in:active,inactive');
        expect($errors)->not->toBeEmpty();

        // In list
        $errors = $delegate->validate('status', 'active', 'in:active,inactive');
        expect($errors)->toBeEmpty();
    });

    it('validates numeric rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Not numeric
        $errors = $delegate->validate('age', 'twenty', 'numeric');
        expect($errors)->not->toBeEmpty();

        // Numeric
        $errors = $delegate->validate('age', '25', 'numeric');
        expect($errors)->toBeEmpty();
    });

    it('uses allData for cross-field validation with same rule', function () {
        $delegate = new LaravelValidationDelegate;

        $allData = [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        // Matching confirmation
        $errors = $delegate->validate('password', 'secret123', 'confirmed', $allData);
        expect($errors)->toBeEmpty();

        // Non-matching confirmation
        $allData['password_confirmation'] = 'different';
        $errors = $delegate->validate('password', 'secret123', 'confirmed', $allData);
        expect($errors)->not->toBeEmpty();
    });

    it('uses allData for cross-field validation with different rule', function () {
        $delegate = new LaravelValidationDelegate;

        $allData = [
            'password' => 'secret123',
            'old_password' => 'oldpass',
        ];

        // Values are different - passes
        $errors = $delegate->validate('password', 'secret123', 'different:old_password', $allData);
        expect($errors)->toBeEmpty();

        // Values are same - fails
        $allData['old_password'] = 'secret123';
        $errors = $delegate->validate('password', 'secret123', 'different:old_password', $allData);
        expect($errors)->not->toBeEmpty();
    });

    it('validates regex rule', function () {
        $delegate = new LaravelValidationDelegate;

        // Matches pattern
        $errors = $delegate->validate('code', 'ABC123', 'regex:/^[A-Z]+[0-9]+$/');
        expect($errors)->toBeEmpty();

        // Does not match pattern
        $errors = $delegate->validate('code', '123abc', 'regex:/^[A-Z]+[0-9]+$/');
        expect($errors)->not->toBeEmpty();
    });

    it('returns an error instead of throwing for an unknown rule', function () {
        $delegate = new LaravelValidationDelegate;

        // A typo'd/unknown rule makes Laravel throw BadMethodCallException; the
        // delegate must catch it and surface a form error, not 500 the request.
        $errors = $delegate->validate('name', 'John', 'this_rule_does_not_exist');

        expect($errors)->toBeArray()->not->toBeEmpty();
        expect($errors[0])->toContain('name');
    });

    it('validates nullable combined with other rules', function () {
        $delegate = new LaravelValidationDelegate;

        // Empty value with nullable passes
        $errors = $delegate->validate('website', '', 'nullable|url');
        expect($errors)->toBeEmpty();

        // Valid URL with nullable passes
        $errors = $delegate->validate('website', 'https://example.com', 'nullable|url');
        expect($errors)->toBeEmpty();

        // Invalid URL with nullable fails
        $errors = $delegate->validate('website', 'not-a-url', 'nullable|url');
        expect($errors)->not->toBeEmpty();
    });
});
