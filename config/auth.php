<?php

use App\Models\Customer;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'api',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'customer_api' => [
            'driver' => 'jwt',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'customer_refresh_cookie' => [
        'name' => env('CUSTOMER_AUTH_REFRESH_COOKIE', 'customer_refresh_token'),
        'ttl_days' => (int) env('CUSTOMER_AUTH_REFRESH_COOKIE_TTL_DAYS', env('AUTH_REFRESH_COOKIE_TTL_DAYS', 30)),
        'path' => env('CUSTOMER_AUTH_REFRESH_COOKIE_PATH', '/'),
        'domain' => (($customerRefreshCookieDomain = env('CUSTOMER_AUTH_REFRESH_COOKIE_DOMAIN', env('AUTH_REFRESH_COOKIE_DOMAIN'))) !== null && trim((string) $customerRefreshCookieDomain) !== '') ? trim((string) $customerRefreshCookieDomain) : null,
        'secure' => filter_var(env('CUSTOMER_AUTH_REFRESH_COOKIE_SECURE', env('AUTH_REFRESH_COOKIE_SECURE', false)), FILTER_VALIDATE_BOOL),
        'same_site' => env('CUSTOMER_AUTH_REFRESH_COOKIE_SAME_SITE', env('AUTH_REFRESH_COOKIE_SAME_SITE', 'lax')),
    ],

    'customer_access_ttl_minutes' => (int) env('CUSTOMER_AUTH_ACCESS_TTL_MINUTES', 43200),

    'refresh_cookie' => [
        'name' => env('AUTH_REFRESH_COOKIE', 'refresh_token'),
        'ttl_days' => (int) env('AUTH_REFRESH_COOKIE_TTL_DAYS', 30),
        'domain' => env('AUTH_REFRESH_COOKIE_DOMAIN'),
        'secure' => filter_var(env('AUTH_REFRESH_COOKIE_SECURE', false), FILTER_VALIDATE_BOOL),
        'same_site' => env('AUTH_REFRESH_COOKIE_SAME_SITE', 'lax'),
    ],

];
