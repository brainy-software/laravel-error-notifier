<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Error Notifier Master Switch
    |--------------------------------------------------------------------------
    |
    | When set to null, the notifier automatically activates if at least one
    | webhook URL (Slack or Discord) is configured, typically in staging
    | or production environments. Set explicitly to true/false to override.
    |
    */
    'enabled' => env('ERROR_NOTIFIER_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Environments to Notify
    |--------------------------------------------------------------------------
    |
    | By default, notifications will only be triggered when running in
    | these environments. Use ['*'] to trigger in all environments.
    |
    */
    'environments' => [
        'production',
        'staging',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure destination webhooks. Multiple channels can be active simultaneously.
    |
    */
    'channels' => [
        'slack' => [
            'enabled' => env('ERROR_NOTIFIER_SLACK_ENABLED', true),
            'webhook_url' => env('ERROR_NOTIFIER_SLACK_WEBHOOK', null),
            'channel' => env('ERROR_NOTIFIER_SLACK_CHANNEL', null),
            'username' => env('ERROR_NOTIFIER_SLACK_USERNAME', 'Error Notifier'),
            'icon_emoji' => env('ERROR_NOTIFIER_SLACK_ICON', ':rotating_light:'),
        ],
        'discord' => [
            'enabled' => env('ERROR_NOTIFIER_DISCORD_ENABLED', true),
            'webhook_url' => env('ERROR_NOTIFIER_DISCORD_WEBHOOK', null),
            'username' => env('ERROR_NOTIFIER_DISCORD_USERNAME', 'Error Notifier'),
            'avatar_url' => env('ERROR_NOTIFIER_DISCORD_AVATAR', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling / Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent notification spam when identical exceptions recur rapidly.
    | Identical exceptions within the period (in seconds) are suppressed.
    |
    */
    'throttle' => [
        'enabled' => env('ERROR_NOTIFIER_THROTTLE_ENABLED', true),
        'period' => (int) env('ERROR_NOTIFIER_THROTTLE_PERIOD', 600), // 10 minutes
        'cache_store' => env('ERROR_NOTIFIER_CACHE_STORE', null), // null uses default cache
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exceptions in this list will never trigger notifications.
    |
    */
    'ignored_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Routing\Exceptions\InvalidSignatureException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Parameter Masking
    |--------------------------------------------------------------------------
    |
    | Keys matching these patterns will be masked before sending out.
    |
    */
    'mask_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'access_token',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stack Trace Display Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of application-level trace frames to display in the message.
    |
    */
    'max_trace_lines' => 12,
];
