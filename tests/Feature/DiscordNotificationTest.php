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
}
