<?php

declare(strict_types=1);

use Flick\Flick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users table for database validation rules
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('name');
        $table->timestamps();
    });

    // Create a test user for unique/exists validation
    DB::table('users')->insert([
        'email' => 'existing@example.com',
        'name' => 'Existing User',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    Flick::resetDefaultRequest();
    Flick::resetDefaultSession();
    Flick::resetDefaultValidationDelegate();
    Flick::resetDefaultCsrfTokenGenerator();
});

describe('Flick Laravel Validation Integration', function () {
    it('validates form with mixed Flick and Laravel rules - all passing', function () {
        // Simulate a POST request with valid data
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'name' => 'John Doe',
            'email' => 'john@example.com', // Valid email (Flick), unique in users table (Laravel)
            'age' => '25',
        ]);
        $this->app['request']->setMethod('POST');

        // Create Flick with validation delegate
        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false, // Disable CSRF for testing
            'echo' => false,
        ]);

        // Validate with mixed rules
        $form->validate->input('name', ['required', 'min:2'], []);
        $form->validate->input('email', ['required', 'email', 'unique:users,email'], []);
        $form->validate->input('age', ['required', 'numeric'], []);

        expect($form->ok())->toBeTrue();
        expect($form->getErrors())->toBeEmpty();
    });

    it('validates form with mixed rules - Flick rule fails', function () {
        // Simulate a POST request with invalid data (name too short)
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'name' => 'J', // Too short - fails Flick's min:2
            'email' => 'john@example.com',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('name', ['required', 'min:2'], []);
        $form->validate->input('email', ['required', 'email'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('name'))->toBeTrue();
        expect($form->hasError('email'))->toBeFalse();
    });

    it('validates form with mixed rules - Laravel rule fails', function () {
        // Simulate a POST request with email that already exists
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'name' => 'Jane Doe',
            'email' => 'existing@example.com', // Already in database - fails Laravel's unique
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('name', ['required', 'min:2'], []);
        $form->validate->input('email', ['required', 'email', 'unique:users,email'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('name'))->toBeFalse();
        expect($form->hasError('email'))->toBeTrue();
        expect($form->getError('email'))->toContain('email');
    });

    it('validates with Laravel exists rule', function () {
        // Simulate a POST request with non-existent user reference
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'user_id' => '999', // Doesn't exist
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('user_id', ['required', 'exists:users,id'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('user_id'))->toBeTrue();
    });

    it('validates with Laravel confirmed rule using all form data', function () {
        // Simulate a POST request with matching password confirmation
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        // Use Laravel's confirmed rule (not Flick's)
        $form->validate->input('password', ['required', 'min:6', 'confirmed'], []);

        expect($form->ok())->toBeTrue();
        expect($form->getErrors())->toBeEmpty();
    });

    it('validates with Laravel confirmed rule - mismatch fails', function () {
        // Simulate a POST request with non-matching password confirmation
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'password' => 'secret123',
            'password_confirmation' => 'different456',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('password', ['required', 'min:6', 'confirmed'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('password'))->toBeTrue();
    });

    it('getErrors returns all accumulated errors from mixed validation', function () {
        // Simulate a POST request with multiple invalid fields
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'name' => '', // Fails required (Flick)
            'email' => 'existing@example.com', // Fails unique (Laravel)
            'website' => 'not-a-url', // Fails url (Flick)
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('name', ['required'], []);
        $form->validate->input('email', ['required', 'email', 'unique:users,email'], []);
        $form->validate->input('website', ['url'], []);

        expect($form->ok())->toBeFalse();

        $errors = $form->getErrors();
        expect($errors)->toHaveKey('name');
        expect($errors)->toHaveKey('email');
        expect($errors)->toHaveKey('website');
        expect(count($errors))->toBe(3);
    });

    it('validates with Laravel size rule', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'code' => 'ABC', // size:5 expects exactly 5 characters
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('code', ['required', 'size:5'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('code'))->toBeTrue();
    });

    it('validates with Laravel date_format rule', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'birth_date' => '2000-01-15',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('birth_date', ['required', 'date_format:Y-m-d'], []);

        expect($form->ok())->toBeTrue();
        expect($form->getErrors())->toBeEmpty();
    });

    it('validates with Laravel date_format rule - invalid format fails', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'birth_date' => '01/15/2000', // Wrong format
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('birth_date', ['required', 'date_format:Y-m-d'], []);

        expect($form->ok())->toBeFalse();
        expect($form->hasError('birth_date'))->toBeTrue();
    });

    it('validates with Flick rules when Laravel delegate returns no errors', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            '_token' => 'test-token',
            'username' => 'john_doe',
            'email' => 'john@example.com',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        // Mix of Flick rules (alphaDash, email) - no Laravel rules
        $form->validate->input('username', ['required', 'alphaDash'], []);
        $form->validate->input('email', ['required', 'email'], []);

        expect($form->ok())->toBeTrue();
    });

    // Bug #5 — with the delegate installed, a comma-argument Flick rule written
    // as a string must still parse. The delegate previously claimed every rule,
    // so 'between:18,65' split into ['between:18','65'] and failed.
    it('parses comma-argument string rules correctly with the delegate installed', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            'age' => '30',
            'color' => 'green',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('age', 'between:18,65', []);
        $form->validate->input('color', 'in:red,green,blue', []);

        expect($form->hasError('age'))->toBeFalse();
        expect($form->hasError('color'))->toBeFalse();
        expect($form->ok())->toBeTrue();
    });

    it('still fails a comma-argument string rule when the value is out of range', function () {
        $this->app['request']->merge([
            '_id' => 'testForm',
            'age' => '9',
        ]);
        $this->app['request']->setMethod('POST');

        $form = new Flick([
            'id' => 'testForm',
            'csrf' => false,
            'echo' => false,
        ]);

        $form->validate->input('age', 'between:18,65', []);

        expect($form->hasError('age'))->toBeTrue();
    });
});
