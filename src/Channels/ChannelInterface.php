<?php

namespace Brainy\ErrorNotifier\Channels;

use Throwable;

interface ChannelInterface
{
    /**
     * Send an exception notification.
     *
     * @param Throwable $exception
     * @param array<string, mixed> $context
     * @return bool Whether the notification was successfully sent.
     */
    public function send(Throwable $exception, array $context): bool;

    /**
     * Determine if this channel is properly configured and enabled.
     */
    public function isEnabled(): bool;
}
