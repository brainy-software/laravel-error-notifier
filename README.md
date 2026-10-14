# Laravel Error Notifier (Slack & Discord 対応)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Total Downloads](https://img.shields.io/packagist/dt/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

サーバー構築や外部 SaaS（Sentry や Datadog など）の契約が一切不要な、Laravel 専用の軽量リアルタイムエラー通知パッケージです。  
未処理の例外や 500 系エラーを、見やすい **Slack**（Block Kit 形式）や **Discord**（Embed 形式）へ即座にリッチ通知します。

---

> ### 🚀 Powered by [B-Beacon](https://b-beacon.biz)
> **[B-Beacon](https://b-beacon.biz)** は、開発チームの生産性とシステムの信頼性を最大化する次世代のクラウド監視・開発者支援プラットフォームです。  
> 本パッケージは、B-Beacon のエラー監視基盤のエッセンスを抽出し、誰でも 3 分で Laravel アプリに導入できる OSS として一般公開されています。
>
> 👉 [B-Beacon の詳細と無料トライアルはこちら](https://b-beacon.biz)

---

## ✨ 主な特徴

- **⚡ ゼロコンフィグ即時導入**:
  - `composer require` して `.env` に Webhook URL を設定するだけ。Laravel Package Discovery により追加コード不要ですぐに動きます。
- **🎨 リッチで見やすい通知デザイン**:
  - **Slack (Block Kit)**: 例外クラス名、エラーメッセージ、発生URL/HTTPメソッド、クライアントIP、ログインユーザー、アプリコードに絞ったスタックトレースを綺麗にカード表示。
  - **Discord (Embeds)**: カラーリングされた見やすいフィールド構成で通知。
- **🛡️ 通知の連打防止（スロットリング機能）**:
  - Laravel のキャッシュ機能（Cache）を活用し、同じエラーが短時間に大量発生しても通知がパンクしない仕組み（デフォルト: 同一エラーは10分間に1回のみ通知）。
- **🔒 機密データの自動マスキング**:
  - リクエストパラメータ内のパスワード、APIトークン、シークレット鍵、クレジットカード番号などを自動的に `********` に置換して送信。
- **🎯 不要な例外の除外フィルター**:
  - 404（NotFound）、422（バリデーションエラー）、403（アクセス拒否）、419（CSRFトークン不一致）など、対応不要なクライアント起因のエラーはデフォルトで自動除外。
- **🧩 Laravel 11 & 12 完全対応**:
  - 最新の Laravel 例外ハンドリング機構（`bootstrap/app.php`）に自動適応。

---

## 📦 インストール方法

Composer 経由でパッケージをインストールします：

```bash
composer require brainy/laravel-error-notifier
```

---

## ⚙️ クイックスタート（3分で完了）

`.env` ファイルに通知先の Webhook URL を設定するだけで完了です：

```env
# Slack Incoming Webhook URL
ERROR_NOTIFIER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# (オプション) Discord Webhook URL
ERROR_NOTIFIER_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR/DISCORD/WEBHOOK
```

これだけで、本番環境（`production`）およびステージング環境（`staging`）で未処理の例外が発生した際、自動的に Slack / Discord へ通知が届きます。

---

## 🛠️ 高度なカスタマイズ

通知対象の環境、スロットリング期間、除外する例外クラス、マスキングキーなどを変更したい場合は、設定ファイルを公開（パブリッシュ）してください：

```bash
php artisan vendor:publish --tag="error-notifier-config"
```

実行すると `config/error-notifier.php` が生成されます：

```php
return [
    // マスター有効フラグ (null の場合、本番・ステージング環境かつ Webhook 設定があれば自動有効化)
    'enabled' => env('ERROR_NOTIFIER_ENABLED', null),

    // 通知を有効にする環境一覧
    'environments' => [
        'production',
        'staging',
    ],

    // 通知チャネル設定
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

    // 通知の連打防止（スロットリング）設定
    'throttle' => [
        'enabled' => env('ERROR_NOTIFIER_THROTTLE_ENABLED', true),
        'period' => (int) env('ERROR_NOTIFIER_THROTTLE_PERIOD', 600), // 秒単位 (600秒 = 10分)
        'cache_store' => env('ERROR_NOTIFIER_CACHE_STORE', null), // null の場合はデフォルトのキャッシュドライバを使用
    ],

    // 通知を除外する例外クラス一覧
    'ignored_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Routing\Exceptions\InvalidSignatureException::class,
    ],

    // 自動マスキングするリクエストパラメータのキー名
    'mask_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
        'credit_card',
    ],

    // 通知内に表示するスタックトレースの最大行数
    'max_trace_lines' => 12,
];
```

---

## 🧪 テストの実行

パッケージのユニットテストを実行するには、以下のコマンドを使用します：

```bash
composer test
```

または直接 PHPUnit を実行：

```bash
./vendor/bin/phpunit
```

---

## 🤝 コントリビューション

バグ報告、改善要望、プルリクエストを歓迎しています！

---

## 📄 ライセンス

本ソフトウェアは [MIT License](LICENSE) の下で公開されています。  
Copyright (c) 2026 [Brainy Software Co., Ltd.](https://brainy-software.co.jp)
