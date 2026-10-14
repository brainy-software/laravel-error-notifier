<?php

namespace Brainy\ErrorNotifier;

use Brainy\ErrorNotifier\Channels\ChannelInterface;
use Brainy\ErrorNotifier\Channels\DiscordChannel;
use Brainy\ErrorNotifier\Channels\SlackChannel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Throwable;

class ErrorNotifierManager
{
    protected Application $app;
    /**
     * @var array<string, mixed>
     */
    protected array $config;
    protected RateLimiter $rateLimiter;
    protected IgnoredExceptions $ignoredExceptions;
    protected ValueMasker $valueMasker;

    /**
     * @var array<string, ChannelInterface>
     */
    protected array $channels = [];

    /**
     * @param Application $app
     * @param array<string, mixed> $config
     * @param RateLimiter $rateLimiter
     * @param IgnoredExceptions $ignoredExceptions
     * @param ValueMasker $valueMasker
     */
    public function __construct(
        Application $app,
        array $config,
        RateLimiter $rateLimiter,
        IgnoredExceptions $ignoredExceptions,
        ValueMasker $valueMasker
    ) {
        $this->app = $app;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->ignoredExceptions = $ignoredExceptions;
        $this->valueMasker = $valueMasker;

        $this->registerDefaultChannels();
    }

    /**
     * Notify configured channels about the exception if allowed.
     */
    public function notify(Throwable $exception): void
    {
        if (!$this->shouldNotify($exception)) {
            return;
        }

        $context = $this->collectContext();

        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                $channel->send($exception, $context);
            }
        }
    }

    /**
     * Check if notifications are enabled and exception passes filters.
     */
    public function shouldNotify(Throwable $exception): bool
    {
        if (!$this->isMasterEnabled()) {
            return false;
        }

        if ($this->ignoredExceptions->shouldIgnore($exception)) {
            return false;
        }

        if (!$this->rateLimiter->shouldNotify($exception)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if error notification is enabled based on config and environment.
     */
    public function isMasterEnabled(): bool
    {
        $explicit = $this->config['enabled'] ?? null;
        if ($explicit !== null) {
            return (bool) $explicit;
        }

        // Automatic mode: check environment and whether at least one channel has a webhook configured
        $env = $this->app->environment();
        $allowedEnvs = $this->config['environments'] ?? ['production', 'staging'];

        if (!in_array('*', $allowedEnvs, true) && !in_array($env, $allowedEnvs, true)) {
            return false;
        }

        foreach ($this->channels as $channel) {
            if ($channel->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect request and execution context safely.
     *
     * @return array<string, mixed>
     */
    public function collectContext(): array
    {
        $context = [
            'environment' => $this->app->environment(),
        ];

        if ($this->app->runningInConsole()) {
            $context['url'] = 'CLI: ' . implode(' ', $_SERVER['argv'] ?? ['artisan']);
            return $context;
        }

        /** @var Request|null $request */
        $request = $this->app->bound('request') ? $this->app->make('request') : null;
        if (!$request instanceof Request) {
            return $context;
        }

        $context['url'] = $request->fullUrl();
        $context['method'] = $request->method();
        $context['ip'] = $request->ip();

        // Mask and collect request payload
        try {
            $input = $request->all();
            if (!empty($input)) {
                $context['payload'] = $this->valueMasker->mask($input);
            }
        } catch (Throwable) {
            // Ignore input collection error
        }

        // Collect authenticated user if available
        try {
            $user = $request->user();
            if (is_object($user)) {
                $userId = method_exists($user, 'getAuthIdentifier')
                    ? (string) $user->getAuthIdentifier()
                    : (string) ($user->id ?? 'unknown');
                $userEmail = isset($user->email) ? (string) $user->email : null;
                $context['user'] = [
                    'id' => $userId,
                    'email' => $userEmail,
                ];
            }
        } catch (Throwable) {
            // Ignore user resolution failure
        }

        return $context;
    }

    /**
     * Register a custom notification channel.
     */
    public function registerChannel(string $name, ChannelInterface $channel): self
    {
        $this->channels[$name] = $channel;
        return $this;
    }

    /**
     * Initialize default Slack and Discord channels.
     */
    protected function registerDefaultChannels(): void
    {
        $slackConfig = $this->config['channels']['slack'] ?? [];
        $this->channels['slack'] = new SlackChannel($slackConfig);

        $discordConfig = $this->config['channels']['discord'] ?? [];
        $this->channels['discord'] = new DiscordChannel($discordConfig);
    }
}
