<?php

namespace Brainy\ErrorNotifier;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class RateLimiter
{
    protected CacheRepository $cache;
    protected bool $enabled;
    protected int $period;

    public function __construct(CacheRepository $cache, bool $enabled = true, int $period = 600)
    {
        $this->cache = $cache;
        $this->enabled = $enabled;
        $this->period = $period;
    }

    /**
     * Determine if the exception should be notified or throttled.
     *
     * Returns true if allowed (should notify), false if throttled (skip notification).
     */
    public function shouldNotify(Throwable $exception): bool
    {
        if (!$this->enabled || $this->period <= 0) {
            return true;
        }

        $key = $this->resolveCacheKey($exception);

        // Cache::add only sets the value if it doesn't already exist.
        // It returns true if stored (first occurrence in window), false if already present.
        $stored = $this->cache->add($key, 1, $this->period);

        if (!$stored) {
            // Increment duplicate counter for analytics/telemetry if supported
            try {
                $countKey = "{$key}:count";
                $this->cache->increment($countKey);
            } catch (Throwable) {
                // Ignore increment failure on drivers that don't support it
            }
            return false;
        }

        return true;
    }

    /**
     * Get the count of suppressed duplicates for this exception in the current window.
     */
    public function getSuppressedCount(Throwable $exception): int
    {
        $countKey = $this->resolveCacheKey($exception) . ':count';
        return (int) $this->cache->get($countKey, 0);
    }

    /**
     * Generate a unique signature hash for an exception.
     */
    public function resolveCacheKey(Throwable $exception): string
    {
        $signature = implode('|', [
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getCode(),
            // Truncate message to avoid memory overhead
            substr($exception->getMessage(), 0, 150),
        ]);

        return 'error_notifier:' . md5($signature);
    }
}
