<?php

namespace Brainy\ErrorNotifier\Tests\Feature;

use Brainy\ErrorNotifier\ErrorNotifierManager;
use Brainy\ErrorNotifier\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordNotificationTest extends TestCase
{
    public function test_it_sends_rich_discord_notification_when_exception_occurs(): void
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response('ok', 204),
        ]);

        config()->set('error-notifier.enabled', true);
        config()->set('error-notifier.channels.slack.enabled', false);
        config()->set('error-notifier.channels.discord.enabled', true);
        config()->set('error-notifier.channels.discord.webhook_url', 'https://discord.com/api/webhooks/TEST/DISCORD');

        /** @var ErrorNotifierManager $manager */
        $manager = $this->app->make(ErrorNotifierManager::class);

        $exception = new RuntimeException('Test Discord error', 500);
        $manager->notify($exception);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://discord.com/api/webhooks/TEST/DISCORD'
                && isset($data['embeds'])
                && count($data['embeds']) === 1
                && str_contains($data['embeds'][0]['title'], 'RuntimeException')
                && str_contains($data['embeds'][0]['description'], 'Test Discord error');
        });
    }

    public function test_it_customizes_discord_message_with_mention_and_custom_title_and_field_toggles(): void
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response('ok', 204),
        ]);

        config()->set('error-notifier.enabled', true);
        config()->set('error-notifier.channels.slack.enabled', false);
        config()->set('error-notifier.channels.discord.enabled', true);
        config()->set('error-notifier.channels.discord.webhook_url', 'https://discord.com/api/webhooks/TEST/DISCORD');
        config()->set('error-notifier.channels.discord.mention', '@here');
        config()->set('error-notifier.message.title', '⚠️ [{environment}] Discord Alert: {class}');
        config()->set('error-notifier.message.fields.file', false);
        config()->set('error-notifier.message.fields.trace', false);

        /** @var ErrorNotifierManager $manager */
        $manager = $this->app->make(ErrorNotifierManager::class);

        $exception = new RuntimeException('Test Discord customized');
        $manager->notify($exception);

        Http::assertSent(function ($request) {
            $data = $request->data();

            $hasMention = isset($data['content']) && $data['content'] === '@here';
            $embed = $data['embeds'][0] ?? [];
            $hasTitle = isset($embed['title']) && str_contains($embed['title'], 'Discord Alert: RuntimeException');

            $embedJson = json_encode($embed);
            $hasNoFile = !str_contains($embedJson, '📁 File');
            $hasNoTrace = !str_contains($embedJson, '📜 Stack Trace');

            return $hasMention && $hasTitle && $hasNoFile && $hasNoTrace;
        });
    }
}
