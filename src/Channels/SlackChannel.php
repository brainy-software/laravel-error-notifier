<?php

namespace Brainy\ErrorNotifier\Channels;

use Brainy\ErrorNotifier\Formatters\SlackBlockKitFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SlackChannel implements ChannelInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;
    protected SlackBlockKitFormatter $formatter;

    /**
     * @param array<string, mixed> $config
     * @param SlackBlockKitFormatter|null $formatter
     */
    public function __construct(array $config, ?SlackBlockKitFormatter $formatter = null)
    {
        $this->config = $config;
        $this->formatter = $formatter ?? new SlackBlockKitFormatter();
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']) && !empty($this->config['webhook_url']);
    }

    public function send(Throwable $exception, array $context): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $payload = $this->formatter->format($exception, $context, $this->config);
            $response = Http::timeout(5)->post($this->config['webhook_url'], $payload);

            if (!$response->successful()) {
                Log::warning('ErrorNotifier: Failed to post to Slack webhook.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            // Never let notification failures break the main application flow
            Log::warning('ErrorNotifier: Exception while sending Slack notification: ' . $e->getMessage());
            return false;
        }
    }
}
