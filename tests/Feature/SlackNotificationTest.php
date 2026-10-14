<?php

namespace Brainy\ErrorNotifier\Tests\Feature;

use Brainy\ErrorNotifier\ErrorNotifierManager;
use Brainy\ErrorNotifier\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SlackNotificationTest extends TestCase
{
    public function test_it_sends_rich_slack_notification_when_exception_occurs(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        config()->set('error-notifier.enabled', true);
        config()->set('error-notifier.channels.slack.enabled', true);
        config()->set('error-notifier.channels.slack.webhook_url', 'https://hooks.slack.com/services/TEST/SLACK/WEBHOOK');
        config()->set('error-notifier.channels.discord.enabled', false);

        /** @var ErrorNotifierManager $manager */
        $manager = $this->app->make(ErrorNotifierManager::class);

        $exception = new RuntimeException('Test fatal database crash', 500);
        $manager->notify($exception);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://hooks.slack.com/services/TEST/SLACK/WEBHOOK'
                && isset($data['blocks'])
                && count($data['blocks']) >= 4
                && str_contains($data['blocks'][0]['text']['text'], 'RuntimeException')
                && str_contains($data['blocks'][1]['text']['text'], 'Test fatal database crash');
        });
    }

    public function test_it_does_not_send_when_channel_is_disabled(): void
    {
        Http::fake();

        config()->set('error-notifier.enabled', true);
        config()->set('error-notifier.channels.slack.enabled', false);

        /** @var ErrorNotifierManager $manager */
        $manager = $this->app->make(ErrorNotifierManager::class);
        $manager->notify(new RuntimeException('Should not send'));

        Http::assertNothingSent();
    }
}
