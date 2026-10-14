# Laravel Error Notifier (Slack & Discord)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Total Downloads](https://img.shields.io/packagist/dt/brainy/laravel-error-notifier.svg?style=flat-square)](https://packagist.org/packages/brainy/laravel-error-notifier)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

<p align="center">
  <img src="art/slack-preview.png" width="48%" alt="Slack Block Kit Notification" />
  <img src="art/discord-preview.png" width="48%" alt="Discord Rich Embed Notification" />
</p>

**English** | [日本語](#japanese)

---

A lightweight, real-time error notifier for Laravel. No server to run, no SaaS subscription (Sentry, Datadog) to sign up for. Unhandled exceptions and 500 errors are pushed straight to **Slack** (Block Kit) or **Discord** (Embeds) as readable cards.

## Features

- **Zero-config setup**
  `composer require`, then set a webhook URL in `.env`. Laravel's package discovery does the rest — no extra code.
- **Bundled GUI configuration generator**
  `generator.html` is a single HTML file. Preview the notification in your browser and generate the `.env` and `config` code by clicking.
- **Readable notification cards**
  - **Slack (Block Kit)**: exception class, message, request URL and HTTP method, client IP, authenticated user, and a stack trace narrowed to your own code.
  - **Discord (Embeds)**: colour-coded fields laid out for quick scanning.
- **Mentions and message customisation**
  Slack (`@channel`, `@here`) and Discord (`@everyone`, `@here`) push mentions, a configurable title template, and per-field visibility toggles.
- **Throttling**
  Backed by Laravel's cache, so a burst of identical errors will not flood your channel. By default the same error is sent at most once every 10 minutes.
- **Automatic masking of sensitive data**
  Passwords, API tokens, secrets and credit card numbers in the request payload are replaced with `********` before sending.
- **Sensible exception filtering**
  404 (not found), 422 (validation), 403 (access denied) and 419 (CSRF token mismatch) are excluded by default — the errors your users cause, not the ones you need to fix.
- **Laravel 11, 12 and 13**
  Works with the current exception handling setup in `bootstrap/app.php`.

**Requirements:** PHP 8.2+ / Laravel 11, 12 or 13.

---

## How It Works

The package hooks into Laravel transparently. Your existing logging and exception handling keep working exactly as before.

```text
[ Unhandled exception / 500 error in your Laravel app ]
                     │
                     ▼
[ 1. Transparent hook (ExceptionHandler::reportable) ]
   Taps into Laravel's exception handler; existing log output is untouched
                     │
                     ▼
[ 2. Exclusion check (ignored exceptions) ]
   404, 422 and friends stop here
                     │
                     ▼
[ 3. Throttle check (rate limiter) ]
   Builds a signature from class, file, line and message, then checks the cache.
   An identical error within the window is suppressed, so the channel stays usable
                     │
                     ▼
[ 4. Context collection & masking ]
   - Request URL, HTTP method, client IP, authenticated user ID
   - Passwords, tokens and other sensitive keys replaced with ********
   - Stack trace stripped of vendor frames, leaving your own code
                     │
                     ▼
[ 5. Send to the webhook (Slack / Discord) ]
   Slack Block Kit or Discord Embeds, with a 5 second timeout.
   If the request fails or times out, your application is unaffected
```

### Three safeguards for production use

1. **It does not break your error handling.**
   Hooking `ExceptionHandler::reportable()` means `storage/logs/laravel.log` keeps filling as usual, and tools like Sentry or Bugsnag continue to work alongside it.
2. **It fails safely.**
   If Slack or Discord is slow or down, the failure is caught internally. It will never turn into a 500 for your user.
3. **It protects sensitive data.**
   The request payload is walked recursively before sending, and keys such as `password`, `token`, `secret` and `credit_card` are masked.

---

## Installation

```bash
composer require brainy/laravel-error-notifier
```

---

## Quick Start

Set a webhook URL in `.env`. That is the whole setup.

```env
# Slack Incoming Webhook URL
ERROR_NOTIFIER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# (optional) Discord Webhook URL
ERROR_NOTIFIER_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR/DISCORD/WEBHOOK
```

Unhandled exceptions in the `production` and `staging` environments will now be delivered to Slack and Discord.

---

## Getting a Webhook URL

### Slack

1. Go to [Slack API: Your Apps](https://api.slack.com/apps) and choose **Create New App** → **From scratch**.
2. Give it a name (for example `Error Notifier`) and pick your workspace.
3. Open **Incoming Webhooks** in the sidebar and turn **Activate Incoming Webhooks** on.
4. Click **Add New Webhook to Workspace** at the bottom and choose the destination channel.
5. Copy the generated URL (`https://hooks.slack.com/services/...`) into `.env`.

### Discord

1. Click the **gear icon** next to the channel that should receive notifications.
2. Choose **Integrations** → **Webhooks**.
3. Click **New Webhook** and give it a name (for example `Error Notifier`).
4. Click **Copy Webhook URL** and paste it into `.env`.

---

## Testing Your Setup

Trigger a test exception from the command line with `artisan tinker`:

```bash
php artisan tinker --execute="report(new \RuntimeException('Test exception from Laravel Error Notifier'));"
```

To test through the browser, add a temporary route to `routes/web.php`:

```php
Route::get('/test-error', function () {
    throw new \RuntimeException('Test notification from a web request');
});
```

If a card arrives in Slack or Discord, you are done.

---

## GUI Configuration Generator

The package ships with `generator.html`, a configuration generator that runs entirely in your browser.

- **Live preview** — see how the notification will look in Slack and Discord as you change settings.
- **One-click output** — tick the mentions and fields you want, then copy or download the `.env` and `config/error-notifier.php` code.
- **Nothing leaves your machine** — there is no server round trip, so your webhook URL stays local.

```bash
# just open it in a browser
open generator.html
```

---

## Customising the Message

### 1. Mentions

Push a notification to the whole team, or to specific people, when something serious happens.

```env
# Slack: @channel, @here, or a specific user (<@U12345678>)
ERROR_NOTIFIER_SLACK_MENTION="<!channel>"

# Discord: @everyone, @here, or a specific role (<@&ROLE_ID>)
ERROR_NOTIFIER_DISCORD_MENTION="@here"
```

### 2. Custom title

```env
# placeholders: {environment}, {class}, {message}
ERROR_NOTIFIER_TITLE_TEMPLATE="🚨 [{environment}] Incident: {class}"
```

### 3. Per-field visibility

Useful when your security policy says client IPs or request payloads must not reach an external chat service.

```env
# set a field to false to leave it out of the notification
ERROR_NOTIFIER_FIELD_FILE=true       # file and line number
ERROR_NOTIFIER_FIELD_ENV=true        # environment (production / staging)
ERROR_NOTIFIER_FIELD_URL=true        # request URL and HTTP method
ERROR_NOTIFIER_FIELD_IP=false        # client IP
ERROR_NOTIFIER_FIELD_USER=true       # authenticated user ID
ERROR_NOTIFIER_FIELD_PAYLOAD=false   # request payload
ERROR_NOTIFIER_FIELD_TRACE=true      # stack trace
```

---

## Advanced Configuration

Publish the config file to go further:

```bash
php artisan vendor:publish --tag="error-notifier-config"
```

```php
return [
    // Master switch. When null, enabled automatically in production/staging if a webhook is set.
    'enabled' => env('ERROR_NOTIFIER_ENABLED', null),

    // Environments where notifications are sent
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
            'mention' => env('ERROR_NOTIFIER_SLACK_MENTION', null),
        ],
        'discord' => [
            'enabled' => env('ERROR_NOTIFIER_DISCORD_ENABLED', true),
            'webhook_url' => env('ERROR_NOTIFIER_DISCORD_WEBHOOK', null),
            'username' => env('ERROR_NOTIFIER_DISCORD_USERNAME', 'Error Notifier'),
            'avatar_url' => env('ERROR_NOTIFIER_DISCORD_AVATAR', null),
            'mention' => env('ERROR_NOTIFIER_DISCORD_MENTION', null),
        ],
    ],

    'message' => [
        'title' => env('ERROR_NOTIFIER_TITLE_TEMPLATE', null),
        'fields' => [
            'file' => (bool) env('ERROR_NOTIFIER_FIELD_FILE', true),
            'environment' => (bool) env('ERROR_NOTIFIER_FIELD_ENV', true),
            'url' => (bool) env('ERROR_NOTIFIER_FIELD_URL', true),
            'ip' => (bool) env('ERROR_NOTIFIER_FIELD_IP', true),
            'user' => (bool) env('ERROR_NOTIFIER_FIELD_USER', true),
            'payload' => (bool) env('ERROR_NOTIFIER_FIELD_PAYLOAD', true),
            'trace' => (bool) env('ERROR_NOTIFIER_FIELD_TRACE', true),
        ],
    ],

    'throttle' => [
        'enabled' => env('ERROR_NOTIFIER_THROTTLE_ENABLED', true),
        'period' => (int) env('ERROR_NOTIFIER_THROTTLE_PERIOD', 600), // seconds (600 = 10 minutes)
        'cache_store' => env('ERROR_NOTIFIER_CACHE_STORE', null), // null = default cache store
    ],

    // Exceptions that never trigger a notification
    'ignored_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Routing\Exceptions\InvalidSignatureException::class,
    ],

    // Request keys masked before sending
    'mask_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
        'credit_card',
    ],

    // Maximum stack trace lines included in a notification
    'max_trace_lines' => 12,
];
```

---

## Roadmap

- [ ] **LINE** — LINE Messaging API / LINE WORKS, for pushing urgent errors to a phone.
- [ ] **Email** — error reports through Laravel's standard mailer.
- [ ] **Generic webhook (custom JSON)** — forward an arbitrary payload to an internal endpoint, PagerDuty, Opsgenie and the like.
- [ ] **Microsoft Teams** — Incoming Webhook with Adaptive Cards.

---

## Running the Tests

```bash
composer test
```

Or run PHPUnit directly:

```bash
./vendor/bin/phpunit
```

---

## Contributing

Bug reports, feature requests and pull requests are welcome.

---

## License

MIT License. See [LICENSE](LICENSE).
Copyright (c) 2026 [Brainy Software LLC](https://www.brainy-software.co.jp)

---
---

## Japanese

[English](#laravel-error-notifier-slack--discord) | **日本語**

サーバー構築や外部 SaaS（Sentry や Datadog など）の契約が一切不要な、Laravel 専用の軽量リアルタイムエラー通知パッケージです。
未処理の例外や 500 系エラーを、見やすい **Slack**（Block Kit 形式）や **Discord**（Embed 形式）へ即座に通知します。

## ✨ 主な特徴

- **⚡ ゼロコンフィグ即時導入**:
  - `composer require` して `.env` に Webhook URL を設定するだけ。Laravel Package Discovery により追加コード不要ですぐに動きます。
- **🖥️ リアルタイム GUI 設定ジェネレーター同梱**:
  - 単一 HTML ファイルの `generator.html` を同梱。ブラウザでプレビューを見ながら、直感的なクリック操作で `.env` や `config` コードを一発生成できます。
- **🎨 リッチで見やすい通知デザイン**:
  - **Slack (Block Kit)**: 例外クラス名、エラーメッセージ、発生URL/HTTPメソッド、クライアントIP、ログインユーザー、アプリコードに絞ったスタックトレースを綺麗にカード表示。
  - **Discord (Embeds)**: カラーリングされた見やすいフィールド構成で通知。
- **🔔 メンション & メッセージカスタマイズ**:
  - Slack (`@channel`, `@here`) や Discord (`@everyone`, `@here`) のプッシュ通知メンション、タイトルテンプレート変更、各項目の個別表示ON/OFFに完全対応。
- **🛡️ 通知の連打防止（スロットリング機能）**:
  - Laravel のキャッシュ機能（Cache）を活用し、同じエラーが短時間に大量発生しても通知がパンクしない仕組み（デフォルト: 同一エラーは10分間に1回のみ通知）。
- **🔒 機密データの自動マスキング**:
  - リクエストパラメータ内のパスワード、APIトークン、シークレット鍵、クレジットカード番号などを自動的に `********` に置換して送信。
- **🎯 不要な例外の除外フィルター**:
  - 404（NotFound）、422（バリデーションエラー）、403（アクセス拒否）、419（CSRFトークン不一致）など、対応不要なクライアント起因のエラーはデフォルトで自動除外。
- **🧩 Laravel 11, 12, 13 完全対応**:
  - 最新の Laravel 例外ハンドリング機構（`bootstrap/app.php`）に自動適応。

**動作要件:** PHP 8.2 以上 / Laravel 11・12・13

---

## 💡 動作の仕組み (How It Works)

本パッケージがどのようにエラーを検知し、安全に通知しているかの全体フローです。
既存のログ出力や例外ハンドリングを一切阻害しない**透過的かつ安全なフェイルセーフ設計**になっています。

```text
[ Laravel アプリケーションで未処理例外・500エラー発生 ]
                     │
                     ▼
[ 1. 自動フック (ExceptionHandler::reportable) ]
   Laravel 標準の例外ハンドラーに透過的に割り込み（既存のログ記録はそのまま維持）
                     │
                     ▼
[ 2. 除外判定 (Ignored Exceptions) ]
   404 (Not Found) や 422 (Validation) などの通常エラーはここで即座に終了
                     │
                     ▼
[ 3. 連打防止判定 (RateLimiter) ]
   例外シグネチャ（クラス名・ファイル・行番号・メッセージのハッシュ）を生成
   Laravel の Cache を確認し、10分以内の同一エラーであれば通知を抑制（Slack のパンク防止）
                     │
                     ▼
[ 4. 安全なコンテキスト収集 & 機密マスキング ]
   ・発生 URL、HTTP メソッド、クライアント IP、ログインユーザー ID を収集
   ・リクエストデータ内のパスワードやトークン等の機密パラメータを自動で ******** に置換
   ・スタックトレースから vendor 配下の行を除外し、自社コード中心に抜粋
                     │
                     ▼
[ 5. Webhook へリッチ通知 (Slack / Discord) ]
   Slack Block Kit または Discord Embeds 形式で Webhook へ送信（タイムアウト 5 秒）
   ※万が一通知送信が失敗・タイムアウトしても、元のアプリの処理には一切影響を与えません
```

### 安心して本番環境に導入できる 3 つの安全設計

1. **既存のエラーハンドリングを壊さない**:
   Laravel の `ExceptionHandler::reportable()` にフックするため、`storage/logs/laravel.log` へのログ出力や、Sentry・Bugsnag などの他のツールと競合せず共存できます。
2. **完全なフェイルセーフ（通知失敗時もアプリは安全）**:
   Slack や Discord の通信遅延や障害が発生しても、通知処理は内部で安全に catch され、ユーザーへの HTTP レスポンスやアプリの動作を巻き込んでクラッシュすることはありません。
3. **個人情報・機密情報の自動保護**:
   送信前にリクエストデータを再帰的に走査し、`password` や `token`、`secret`、`credit_card` などの機密パラメータを自動的に `********` にマスキングします。

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

## 🔑 Webhook URL の取得方法

### Slack の場合
1. [Slack API: Your Apps](https://api.slack.com/apps) にアクセスし、**「Create New App」** → **「From scratch」** を選択。
2. アプリ名（例: `Error Notifier`）とワークスペースを入力して作成。
3. 左側メニューの **「Incoming Webhooks」** をクリックし、機能を **「Activate Incoming Webhooks」を ON** にします。
4. ページ最下部の **「Add New Webhook to Workspace」** をクリックし、通知先チャンネルを選択して許可します。
5. 生成された **Webhook URL**（`https://hooks.slack.com/services/...`）をコピーして `.env` に設定します。

### Discord の場合
1. 通知を受信したいチャンネルの横にある **歯車アイコン（チャンネルの編集）** をクリックします。
2. 左メニューの **「連携」**（Integrations）→ **「ウェブフック」** を選択します。
3. **「新しいウェブフック」** をクリックし、任意の名前（例: `Error Notifier`）を設定します。
4. **「ウェブフック URL をコピー」** ボタンをクリックし、コピーした URL を `.env` に設定します。

---

## 🧪 通知テストの実行方法（導入確認）

設定が完了したら、`artisan tinker` を使ってコマンドラインから 1行でテスト例外を発行し、通知が届くか確認できます：

```bash
php artisan tinker --execute="report(new \RuntimeException('Laravel Error Notifier のテスト例外です！'));"
```

ブラウザ経由で確認したい場合は、`routes/web.php` に一時的なテストルートを追加してアクセスしてください：

```php
Route::get('/test-error', function () {
    throw new \RuntimeException('Webリクエスト経由のエラー通知テストです！');
});
```

実行後、Slack または Discord にリッチカード通知が即座に届けば導入成功です。

---

## 🖥️ リアルタイム設定ジェネレーター (GUI)

本パッケージには、ブラウザで開くだけですぐに使える **GUI 設定ジェネレーター（`generator.html`）** が同梱されています。

- **リアルタイム Live プレビュー**: Slack や Discord でどのように表示されるかをその場でプレビュー確認できます。
- **ワンクリック生成**: メンションや非表示にしたい項目にチェックを入れるだけで、`.env` および `config/error-notifier.php` のコードを自動生成し、コピーまたはダウンロードできます。
- **完全ローカル完結**: サーバー送信なし。機密情報（Webhook URL等）が外部に漏れる心配はありません。

```bash
# generator.html をブラウザで開くだけ
open generator.html
```

---

## 🔔 メッセージカスタマイズ & メンション

Slack / Discord への通知内容をプロジェクトの要件やセキュリティポリシーに合わせて柔軟にカスタマイズできます。

### 1. メンション機能
重大エラーの発生時に、チーム全体や特定のメンバーへプッシュ通知を飛ばすことができます。

```env
# Slack: @channel, @here, 特定ユーザー (<@U12345678>)
ERROR_NOTIFIER_SLACK_MENTION="<!channel>"

# Discord: @everyone, @here, 特定ロール (<@&ロールID>)
ERROR_NOTIFIER_DISCORD_MENTION="@here"
```

### 2. カスタムタイトル
通知カードの最上部に表示されるタイトルテンプレートを変更できます。

```env
# プレースホルダー: {environment}, {class}, {message}
ERROR_NOTIFIER_TITLE_TEMPLATE="🚨 [{environment}] 重大障害検知: {class}"
```

### 3. 表示フィールドの個別 ON / OFF
社内セキュリティ規程で「クライアントIPやリクエストパラメータを社外チャットに送信したくない」場合など、項目単位で非表示にできます。

```env
# 通知に含めないフィールドを false に設定
ERROR_NOTIFIER_FIELD_FILE=true       # 発生ファイル・行番号
ERROR_NOTIFIER_FIELD_ENV=true        # 環境名 (production / staging)
ERROR_NOTIFIER_FIELD_URL=true        # リクエストURL・HTTPメソッド
ERROR_NOTIFIER_FIELD_IP=false        # クライアントIP（社内ポリシー等で非表示にする場合）
ERROR_NOTIFIER_FIELD_USER=true       # ログインユーザーID
ERROR_NOTIFIER_FIELD_PAYLOAD=false   # リクエスト入力データ
ERROR_NOTIFIER_FIELD_TRACE=true      # スタックトレース
```

---

## 🛠️ 高度な設定 (config/error-notifier.php)

さらに詳細な設定を行いたい場合は、設定ファイルを公開（パブリッシュ）してください：

```bash
php artisan vendor:publish --tag="error-notifier-config"
```

生成される `config/error-notifier.php` の構造：

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
            'mention' => env('ERROR_NOTIFIER_SLACK_MENTION', null),
        ],
        'discord' => [
            'enabled' => env('ERROR_NOTIFIER_DISCORD_ENABLED', true),
            'webhook_url' => env('ERROR_NOTIFIER_DISCORD_WEBHOOK', null),
            'username' => env('ERROR_NOTIFIER_DISCORD_USERNAME', 'Error Notifier'),
            'avatar_url' => env('ERROR_NOTIFIER_DISCORD_AVATAR', null),
            'mention' => env('ERROR_NOTIFIER_DISCORD_MENTION', null),
        ],
    ],

    // メッセージカスタマイズ & 表示フィールド
    'message' => [
        'title' => env('ERROR_NOTIFIER_TITLE_TEMPLATE', null),
        'fields' => [
            'file' => (bool) env('ERROR_NOTIFIER_FIELD_FILE', true),
            'environment' => (bool) env('ERROR_NOTIFIER_FIELD_ENV', true),
            'url' => (bool) env('ERROR_NOTIFIER_FIELD_URL', true),
            'ip' => (bool) env('ERROR_NOTIFIER_FIELD_IP', true),
            'user' => (bool) env('ERROR_NOTIFIER_FIELD_USER', true),
            'payload' => (bool) env('ERROR_NOTIFIER_FIELD_PAYLOAD', true),
            'trace' => (bool) env('ERROR_NOTIFIER_FIELD_TRACE', true),
        ],
    ],

    // 通知の連打防止（スロットリング）設定
    'throttle' => [
        'enabled' => env('ERROR_NOTIFIER_THROTTLE_ENABLED', true),
        'period' => (int) env('ERROR_NOTIFIER_THROTTLE_PERIOD', 600), // 秒単位 (600秒 = 10分)
        'cache_store' => env('ERROR_NOTIFIER_CACHE_STORE', null), // null の場合はデフォルトキャッシュ
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

## 🗺️ 今後のロードマップ (Roadmap)

今後のアップデートにて、以下の機能拡張を順次予定しています。

- [ ] **💬 LINE 通知対応**:
  - LINE Messaging API / LINE WORKS と連携し、緊急性の高いエラーをスマートフォンへ直接プッシュ通知。
- [ ] **✉️ Email 通知対応**:
  - Laravel の標準 Mailer を活用したエラーレポートメール送信。
- [ ] **🌐 汎用 Webhook (Custom JSON) 対応**:
  - 社内の専用エンドポイントや PagerDuty、Opsgenie 等へ任意の JSON ペイロードを転送。
- [ ] **👥 Microsoft Teams 対応**:
  - Teams の Incoming Webhook (Adaptive Cards) 形式でのリッチ通知。

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

バグ報告、改善要望、プルリクエストを歓迎しています。

---

## 📄 ライセンス

本ソフトウェアは [MIT License](LICENSE) の下で公開されています。
Copyright (c) 2026 [合同会社ブレイニーソフトウェア (Brainy Software LLC)](https://www.brainy-software.co.jp)
