# Laravel Error Notifier (Slack & Discord)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Total Downloads](https://img.shields.io/packagist/dt/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

A lightweight, zero-configuration real-time error notifier for Laravel applications.  
Deliver rich, actionable alerts directly to **Slack** (via Block Kit) and **Discord** (via Embeds) without setting up or paying for heavy SaaS platforms (e.g. Sentry, Datadog).

---

> ### 🚀 Powered by [B-Beacon](https://b-beacon.biz)
> **[B-Beacon](https://b-beacon.biz)** は、開発チームの生産性とシステムの信頼性を最大化する次世代のクラウド監視・開発者支援プラットフォームです。  
> 本パッケージは、B-Beacon のエラー監視基盤のエッセンスを抽出し、誰でも 3 分で Laravel に導入できるスタンドアローン OSS として一般公開されています。
>
> 👉 [B-Beacon の詳細と無料トライアルはこちら](https://b-beacon.biz)

---

## ✨ Features

- **⚡ Zero-Config Instant Setup**: `composer require` and set your webhook URL in `.env`. Works out of the box with Laravel Package Discovery.
- **🎨 Rich Alert Layouts**:
  - **Slack Block Kit**: Clean cards with exception class, message, request method/URL, client IP, auth user, and stack trace.
  - **Discord Embeds**: Beautifully formatted, colored embed cards with field grids.
- **🛡️ Built-in Rate Limiting / Throttling**:
  - Automatically suppresses identical duplicate errors for a configurable window (default: 10 minutes) using Laravel's Cache, preventing Slack channel flooding during traffic spikes.
- **🔒 Sensitive Data Masking**:
  - Automatically masks passwords, tokens, API keys, and credit card numbers from request inputs.
- **🎯 Smart Exception Filtering**:
  - Common non-critical exceptions (404 Not Found, 422 Validation Error, 403 Forbidden, 419 Token Mismatch) are excluded by default.
- **🧩 Laravel 11 & 12 Ready**: Fully compatible with the modern Laravel exception architecture.

---

## 📦 Installation

Install via Composer:

```bash
composer require brainy/laravel-error-notifier
```

---

## ⚙️ Quick Start (Zero Config)

Add your Webhook URL to your `.env` file:

```env
# Slack Incoming Webhook
ERROR_NOTIFIER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# (Optional) Discord Webhook
ERROR_NOTIFIER_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR/DISCORD/WEBHOOK
```

That's it! Any unhandled 500 error or fatal exception in `production` or `staging` will now trigger instant alerts to your configured channel.

---

## 🛠️ Advanced Configuration

To customize channels, throttling, ignored exceptions, or sensitive keys, publish the configuration file:

```bash
php artisan vendor:publish --tag="error-notifier-config"
```

This generates `config/error-notifier.php`:

```php
return [
    // Master switch (null = auto-activate if webhook is present in production/staging)
    'enabled' => env('ERROR_NOTIFIER_ENABLED', null),

    'environments' => [
        'production',
        'staging',
    ],

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
        ],
    ],

    // Throttling configuration
    'throttle' => [
        'enabled' => env('ERROR_NOTIFIER_THROTTLE_ENABLED', true),
        'period' => (int) env('ERROR_NOTIFIER_THROTTLE_PERIOD', 600), // seconds
        'cache_store' => env('ERROR_NOTIFIER_CACHE_STORE', null),
    ],

    // Exceptions that will never trigger notifications
    'ignored_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Routing\Exceptions\InvalidSignatureException::class,
    ],

    // Sensitive keys to automatically mask
    'mask_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
        'credit_card',
    ],

    // Application stack trace line limit
    'max_trace_lines' => 12,
];
```

---

## 🧪 Testing

Run package tests:

```bash
composer test
```

Or directly via PHPUnit:

```bash
./vendor/bin/phpunit
```

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome! Feel free to open an issue or pull request on GitHub.

---

## 📄 License

This software is released under the [MIT License](LICENSE).  
Copyright (c) 2026 [Brainy Software Co., Ltd.](https://brainy-software.co.jp)
