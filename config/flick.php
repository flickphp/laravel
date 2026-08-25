<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Form View Template
    |--------------------------------------------------------------------------
    |
    | The CSS framework template to use for form rendering. Flick includes
    | templates for popular frameworks out of the box.
    |
    | Supported: "flick", "bootstrap", "bulma", "tailwind", "foundation", "materialize"
    |
    */
    'views' => env('FLICK_VIEWS', 'tailwind'),

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Flick has built-in CSRF protection, but Laravel provides its own via
    | its request forgery middleware. Set to false to use Laravel's CSRF
    | (recommended) or true to use Flick's built-in CSRF tokens.
    |
    | false is a trust, not a check: Flick renders Laravel's token and assumes
    | Laravel's middleware validated it. That holds for a route in the `web`
    | group. A form posted to a route OUTSIDE that group has no CSRF check from
    | either side — keep form routes in `web`, or use 'strict'.
    |
    | 'strict' additionally has Flick compare the posted _token against
    | Laravel's own token itself. It is opt-in because a client that sends the
    | token only as a header (Axios sends X-XSRF-TOKEN automatically) posts no
    | _token field, and Flick would reject that submission.
    |
    */
    'csrf' => false,

    /*
    |--------------------------------------------------------------------------
    | Honeypot Field
    |--------------------------------------------------------------------------
    |
    | The name of the honeypot field used for spam protection. This field
    | should be hidden via CSS and left empty by real users. Bots that
    | fill it out will have their submissions silently rejected.
    |
    | Set to null or empty string to disable honeypot protection.
    |
    */
    'honeypot' => 'website_url',

    /*
    |--------------------------------------------------------------------------
    | Session Auto-Start
    |--------------------------------------------------------------------------
    |
    | Whether Flick should automatically start PHP sessions. Since Laravel
    | manages sessions via its middleware, this should be false to prevent
    | conflicts. Only set to true if using Flick outside Laravel's request
    | lifecycle.
    |
    */
    'sessionAutoStart' => false,

    /*
    |--------------------------------------------------------------------------
    | Echo Output
    |--------------------------------------------------------------------------
    |
    | Whether form methods should echo their output directly (true) or
    | return strings for manual output (false). In Blade templates, you
    | typically want this false and use {!! $form->text(...) !!} syntax.
    |
    */
    'echo' => false,
];
