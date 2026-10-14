# 【投稿・入稿指示書】Laravel専用エラー通知OSS公開記事

本ドキュメントは、OSSパッケージ `brainy/laravel-error-notifier` の公開に伴う技術記事（Zenn / 自社ブログ / Qiita 共通）の入稿指示書および記事本文ドラフトです。
担当者の方は、以下のメタ情報と指示に従って各媒体へ入稿・公開作業を行ってください。

---

## 📋 入稿メタ情報（担当者向け指示）

| 項目 | 内容・指定 |
| :--- | :--- |
| **対象プラットフォーム** | 1. **Zenn** (`https://zenn.dev/brainy_software`)<br>2. **自社ブログ** (`https://www.brainy-software.co.jp/blog/`)<br>3. **Qiita** (社内アカウント) |
| **記事タイトル（推奨案）** | **Sentryを入れるほどじゃないけど500エラーは通知したい ── Laravel専用エラー通知OSSを作った話** |
| **タイトル候補（別案B）** | **外部SaaS不要・.env1行でSlack/Discordへリッチ通知するLaravelパッケージを作った理由と安全設計** |
| **カテゴリ (Zenn/自社)** | `Tech (技術)` / `Web開発` |
| **アイキャッチ画像 (OGP)** | リポジトリ内 `art/slack-preview.png` と `art/discord-preview.png` を並べた画像、または Slack 通知カードのスクリーンショットを使用。 |
| **タグ / トピック** | `Laravel`, `PHP`, `Slack`, `Discord`, `OSS`, `個人開発` |
| **公開ステータス** | GitHubリポジトリ公開と同時に「公開 (Public)」に設定 |
| **URLスラッグ（自社ブログ）**| `laravel-error-notifier-release` |

---

## 📝 記事本文ドラフト（この下をそのまま入稿してください）

```markdown
# Sentryを入れるほどじゃないけど500エラーは通知したい ── Laravel専用エラー通知OSSを作った話

Webサービスや業務システム、社内受託の開発・運用において、避けて通れないのが「本番環境での500エラーや未処理例外の検知」だ。

ユーザーからの問い合わせで初めて障害に気づく事態は絶対に避けたい。しかし、だからといってSentryやDatadogのような高機能SaaSをすべての案件に導入できるかというと、現実にはコストや運用のハードルがある。

- 「小規模なプロジェクトや社内ツールに月額数千〜数万円の監視SaaSを入れる予算がない」
- 「チームで普段使っているSlackやDiscordに、見やすいカード形式でエラー通知を流したいだけ」
- 「Laravel標準のSlackログ機能（Monolog）はあるが、レイアウトが素っ気なく、**障害時に同一エラーが大量発生するとSlackがパンクする（スロットリングがない）**」
- 「リクエストパラメータに含まれるパスワードやトークンがチャットに流出するのが怖い」

こうした現場の生々しい課題を解決するため、**.env に Webhook URL を1行設定するだけで、Slack（Block Kit）や Discord（Rich Embed）へ安全・美麗にリアルタイム通知するLaravel専用OSSパッケージ** を開発した。

- **GitHub**: [brainy-software/laravel-error-notifier](https://github.com/brainy-software/laravel-error-notifier)
- **Packagist**: `brainy/laravel-error-notifier`
- **ライセンス**: MIT License（商用利用・改変自由）

この記事では、既存ツールとの違い、設計において最も腐心した「フェイルセーフと安全設計」、そしてブラウザ上で設定ファイルをリアルタイム生成できるGUIジェネレーターの仕組みについて共有する。

---

## なぜ既存の通知パッケージでは満足できなかったのか？

LaravelでSlackやDiscordにエラーを通知する手段は、これまでもいくつか存在していた。しかし、実務で安心して使うにはあと一歩足りないケースが多かった。

| 手法 / 既存ツール | メリット | 現場で直面した課題 |
| :--- | :--- | :--- |
| **Laravel標準 Monolog (Slack)** | 設定追加だけで動く | ・**スロットリング機能がない**ため、DB障害などで毎秒数百通の通知が届きSlackがフリーズする<br>・Discordに対応していない<br>・パスワード等の自動マスキングがない |
| **Spatie製 Slack Alerts** | 軽量で使いやすい | ・単に文字列をWebhookで送るだけのラッパーであり、例外の捕捉、スタックトレース整形、コンテキスト収集は**すべて自前実装が必要** |
| **海外製の多機能通知パッケージ** | 多数のチャットに対応 | ・設定ファイルが巨大で難解<br>・日本語情報がなく、メンテナンスが停滞しているものが多い |

私たちが求めていたのは、**「導入コストはゼロに等しく、設定は極限までシンプル。しかし障害時には絶対にチャットをパンクさせず、セキュリティと安定性を担保できるツール」** だった。

---

## パッケージのコア機能と特徴

### 1. Slack (Block Kit) & Discord (Embed) 両対応の専用カードレイアウト
エラー通知は「見た瞬間に状況が把握できること」が最も重要だ。
本パッケージでは、SlackのBlock KitとDiscordのRich Embedを活用し、開発者が初動で必要な情報を綺麗に整理して通知する。

- **例外クラス名 & メッセージ**（目立つ赤色アクセント）
- **発生ファイル名 & 行番号**
- **環境名**（LOCAL / STAGING / PRODUCTION）
- **リクエストURL & HTTPメソッド**
- **クライアントIP & ログイン中ユーザーID**
- **アプリケーションコードに絞ったスタックトレース**（vendorディレクトリの膨大なフレームを自動除外）

### 2. 同一エラーの連打を自動抑制（スロットリング機能）
DBの接続エラーや外部APIのダウンが発生した際、同一のエラーが短時間に数千件発生することがある。通知ツール自体がチャットをパンクさせたり、Webhookのレートリミットに引っかかって停止しては本末転倒だ。

本パッケージでは、Laravelのキャッシュ機能（Cache）を活用し、**「例外クラス名・発生ファイル・行番号・エラーメッセージ」のハッシュシグネチャを生成して、同一エラーの連続通知を自動抑制（デフォルト: 10分間に1回のみ通知）** する。

### 3. 機密パラメータの自動マスキング
フォーム送信時のエラー通知には、リクエストパラメータ（入力データ）が含まれることが多い。
送信前にペイロードを再帰的に走査し、以下のキーに該当する値は自動的に `********` に置換される。

```php
'mask_keys' => [
    'password', 'password_confirmation', 'current_password',
    'token', 'secret', 'api_key', 'access_token',
    'credit_card', 'card_number', 'cvv',
],
```

### 4. 404やバリデーションエラーの自動除外
404（NotFoundHttpException）や422（ValidationException）、CSRFトークン不一致（TokenMismatchException）など、ユーザーの操作起因のエラーで通知が鳴り止まない事態を防ぐため、業務上対応不要な例外はデフォルトで自動除外される。

---

## 最もこだわった「安全設計（フェイルセーフ）」

監視ツールや通知パッケージを導入する際、最も恐ろしいのは**「通知パッケージ側の不具合で、メインのアプリケーションがクラッシュすること」**だ。

本パッケージでは、以下の安全設計を徹底している。

1. **既存のログ出力を一切壊さない**:
   Laravelの `ExceptionHandler::reportable()` に透過的にフックするため、`storage/logs/laravel.log` へのログ書き込みや、Sentry等の併用ツールと競合しない。
2. **完全なフェイルセーフ（通信障害時もアプリは安全）**:
   SlackやDiscordのAPI障害、タイムアウト（5秒）、ネットワーク寸断が発生しても、通知処理は内部で安全に例外処理され、ユーザーへのHTTPレスポンスを巻き込んで500エラーになることは絶対にない。

---

## 3分で終わるクイックスタート

### 1. インストール
```bash
composer require brainy/laravel-error-notifier
```
LaravelのPackage Discoveryに対応しているため、サービスプロバイダの登録作業は不要だ。

### 2. .env に Webhook URL を指定
```env
# Slack Incoming Webhook
ERROR_NOTIFIER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# Discord Webhook (オプション)
ERROR_NOTIFIER_DISCORD_WEBHOOK=https://discord.com/api/webhooks/YOUR/DISCORD/WEBHOOK
```

### 3. 動作テスト
ターミナルから `artisan tinker` を1行叩くだけで、通知カードが届くことを確認できる。

```bash
php artisan tinker --execute="report(new \RuntimeException('テスト例外です！'));"
```

---

## 【世界初？】ブラウザで動くリアルタイムGUIジェネレーター

本パッケージのユニークな機能として、**単一のHTMLファイルで完結する設定ジェネレーター（`generator.html`）を同梱**している。

設定ファイル（`config/error-notifier.php`）の各項目（メンションの有無、タイトルのカスタム、非表示にしたいフィールド、スロットリング期間など）を、**ブラウザ上でSlack/Discordのプレビューを見ながらリアルタイムに生成**できる。

```bash
# パッケージ配下の generator.html をブラウザで開くだけ
open generator.html
```

- クライアントIPやリクエストパラメータを非表示にするトグルスイッチ
- `@channel` や `@here` メンションの入力
- 下部のボタンから `.env` のコピーや、設定ファイルのワンクリックダウンロードが可能

外部サーバーとの通信は一切なく、完全ローカルで動作するため、Webhook URLが外部に漏れる心配もない。

---

## まとめ・今後の予定

個人開発や社内ツール、受託開発の初期フェーズにおいて、「手軽に、でも安全性と見た目には妥協せずエラー監視をしたい」という場面で役立てていただければ幸いだ。

今後は以下の機能拡張も予定している：
- 💬 **LINE 通知対応**（LINE Messaging API / LINE WORKS 連携）
- ✉️ **Email 通知対応**（Laravel標準Mailer連携）
- 🌐 **汎用 Webhook (Custom JSON) 対応**（社内APIやPagerDuty等への転送）
- 👥 **Microsoft Teams 対応**（Adaptive Cards）

バグ報告や機能要望、プルリクエストはお気軽にGitHubリポジトリまでお寄せください！

- **GitHub**: [https://github.com/brainy-software/laravel-error-notifier](https://github.com/brainy-software/laravel-error-notifier)
- **開発元**: [合同会社ブレイニーソフトウェア (Brainy Software LLC)](https://www.brainy-software.co.jp/)
```
