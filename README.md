# Flick Laravel Integration

Laravel adapter package for the [Flick](https://github.com/flickphp/flick) form library.

## Installation

```bash
composer require flickphp/laravel
```

The package auto-registers via Laravel's package discovery.

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=flick-config
```

This creates `config/flick.php` with default settings:

```php
return [
    'views' => 'tailwind',  // Form styling: flick, bootstrap, bulma, tailwind, etc.
    'csrf' => false,        // Disable Flick CSRF (use Laravel's middleware)
    'honeypot' => 'website_url',
    'sessionAutoStart' => false,  // Laravel manages sessions
    'echo' => false,  // Required for Blade: {!! !!} needs the string returned, not echoed
];
```

## Usage

### In Controllers

```php
use Flick\Flick;

class ContactController extends Controller
{
    public function show()
    {
        $form = new Flick(config('flick'));

        return view('contact', ['form' => $form]);
    }

    public function store()
    {
        $form = new Flick(config('flick'));

        if ($form->submitted()) {
            $data = $form->request('Name, Email, Message|textarea');

            if ($form->ok()) {
                // Process form...
                return redirect()->back()->with('success', 'Message sent!');
            }
        }

        return view('contact', ['form' => $form]);
    }
}
```

### In Blade Views

```blade
{!! $form->open('/contact', 'POST') !!}
    {!! $form->text('name', 'Name', '', ['rules' => 'required']) !!}
    {!! $form->email('email', 'Email', '', ['rules' => 'required,email']) !!}
    {!! $form->textarea('message', 'Message', '', ['rules' => 'required']) !!}
    {!! $form->submit('Send Message') !!}
{!! $form->close() !!}
```

### Using the Facade (Optional)

```php
use Flick\Laravel\Facades\Flick;

// Create a form (always creates a new instance)
$form = Flick::make(['views' => 'bootstrap']);
```

## How It Works

This package provides Laravel-specific adapters that implement Flick's abstraction interfaces:

- **LaravelRequest** - Wraps `Illuminate\Http\Request` to provide POST, GET, FILES, cookies, and headers
- **LaravelSession** - Wraps Laravel's session store for multistep forms and CSRF tokens

The service provider automatically injects these adapters via `Flick::setDefaultRequest()` and `Flick::setDefaultSession()`, so any `new Flick()` instance automatically uses Laravel's request and session.

## Using Laravel Validation Rules

This package automatically bridges Laravel's validator with Flick, so you can use Laravel validation rules alongside Flick's native rules:

```php
// Database rules work seamlessly
$form->text('email', 'Email', '', ['rules' => 'required,email,unique:users,email']);
$form->select('category_id', 'Category', '', [
    'options' => ['1' => 'News', '2' => 'Sports'],
    'rules' => 'required,exists:categories,id',
]);

// Cross-field validation
$form->password('password', 'Password', '', ['rules' => 'required,min:8,confirmed']);
$form->password('password_confirmation', 'Confirm Password');

// Mix Flick and Laravel rules freely
$form->text('username', 'Username', '', ['rules' => 'required,alphaDash,unique:users,username']);
```

### Rule Priority

Flick checks its native rules first, then delegates unrecognized rules to Laravel's Validator:

1. **Flick native rules** (e.g., `required`, `email`, `min`, `max`, `alpha`, `alphaDash`) - handled by Flick
2. **Laravel-specific rules** (e.g., `exists`, `unique`, `different`, `mimes`) - delegated to Laravel

This means you can use Flick's concise syntax for common rules while leveraging Laravel's database-aware rules when needed.

Flick's list is longer than it looks, so a rule you think of as Laravel's may
never reach it. `confirmed` is one: Flick has its own, and it looks for
`{field}_confirmation` exactly as Laravel's does, so the check behaves the same
either way — only the wording of the error message differs.

### Custom Error Messages

Custom messages work the same way:

```php
$form->text('email', 'Email', '', [
    'rules' => 'required,unique:users,email',
    'messages' => [
        'required' => 'Please enter your email address.',
        'unique' => 'This email is already registered.',
    ],
]);
```

## CSRF Protection

Laravel's CSRF token is automatically included in your forms. The default configuration (`'csrf' => false`) tells Flick to use Laravel's `csrf_token()` instead of generating its own. **You do not need to add `@csrf`** — Flick injects it automatically:

```blade
{!! $form->open('/contact', 'POST') !!}
    {{-- CSRF token is auto-injected here --}}
    {!! $form->text('name', 'Name') !!}
    {!! $form->submit() !!}
{!! $form->close() !!}
```

If you need Laravel's method spoofing for PUT/PATCH/DELETE requests, use a raw HTML form:

```blade
<form method="POST" action="/contact">
    @csrf
    @method('PUT')
    {!! $form->text('name', 'Name') !!}
    {!! $form->submit() !!}
</form>
```

### Keep form routes in the `web` group

`'csrf' => false` is a trust, not a check: Flick renders Laravel's token and
assumes Laravel's middleware validated it. That holds for a route inside the
`web` middleware group. A form posted to a route **outside** that group gets no
CSRF check from either side.

Keep your form routes in `web`, or set `'csrf' => 'strict'`:

```php
// config/flick.php
'csrf' => 'strict',
```

`'strict'` still renders Laravel's token, but Flick also compares the posted
`_token` against it rather than assuming the check already happened.

It is opt-in rather than the default because a client that sends the token only
as a header posts no `_token` field — Axios sends `X-XSRF-TOKEN` automatically —
and Flick would reject that submission.

| Value | What Flick does |
|-------|-----------------|
| `false` (default) | Renders Laravel's token; trusts Laravel's middleware to validate it |
| `'strict'` | Renders Laravel's token and validates the posted `_token` itself |
| `true` or an integer | Ignores Laravel's token; uses Flick's own session token, integer = timeout in seconds |

## Requirements

- PHP 8.3+
- Laravel 12.x or 13.x
- flickphp/flick ^1.0

## See Also

- [Flick Documentation](https://flickphp.com) - Full Flick documentation
- [Flick Core](https://github.com/flickphp/flick) - Main Flick package
- [Flick Migrate](https://github.com/flickphp/migrate) - Formr to Flick migration tool
- [Flick Pro](https://flickphp.com/pro) - Premium services

## License

MIT
