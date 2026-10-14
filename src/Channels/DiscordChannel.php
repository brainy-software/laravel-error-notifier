<?php

namespace Brainy\ErrorNotifier\Channels;

use Brainy\ErrorNotifier\Formatters\DiscordEmbedFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DiscordChannel implements ChannelInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;
    protected DiscordEmbedFormatter $formatter;

    /**
     * @param array<string, mixed> $config
     * @param DiscordEmbedFormatter|null $formatter
     */
    public function __construct(array $config, ?DiscordEmbedFormatter $formatter = null)
    {
        $this->config = $config;
        $this->formatter = $formatter ?? new DiscordEmbedFormatter();
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
                Log::warning('ErrorNotifier: Failed to post to Discord webhook.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('ErrorNotifier: Exception while sending Discord notification: ' . $e->getMessage());
            return false;
        }
    }
}
