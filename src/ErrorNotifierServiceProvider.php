<?php

namespace Brainy\ErrorNotifier;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ErrorNotifierServiceProvider extends ServiceProvider
{
    /**
     * Register bindings and configurations.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/error-notifier.php',
            'error-notifier'
        );

        $this->app->singleton(ValueMasker::class, function () {
            $maskKeys = config('error-notifier.mask_keys', []);
            return new ValueMasker($maskKeys);
        });

        $this->app->singleton(IgnoredExceptions::class, function () {
            $ignored = config('error-notifier.ignored_exceptions', []);
            return new IgnoredExceptions($ignored);
        });

        $this->app->singleton(RateLimiter::class, function ($app) {
            $storeName = config('error-notifier.throttle.cache_store');
            /** @var CacheRepository $cache */
            $cache = $storeName ? $app['cache']->store($storeName) : $app['cache']->store();
            $enabled = (bool) config('error-notifier.throttle.enabled', true);
            $period = (int) config('error-notifier.throttle.period', 600);

            return new RateLimiter($cache, $enabled, $period);
        });

        $this->app->singleton(ErrorNotifierManager::class, function ($app) {
            return new ErrorNotifierManager(
                $app,
                config('error-notifier', []),
                $app->make(RateLimiter::class),
                $app->make(IgnoredExceptions::class),
                $app->make(ValueMasker::class)
            );
        });
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/error-notifier.php' => config_path('error-notifier.php'),
            ], 'error-notifier-config');
        }

        $this->registerExceptionHandlerHook();
    }

    /**
     * Automatically register reportable hook into Laravel ExceptionHandler.
     */
    protected function registerExceptionHandlerHook(): void
    {
        if (!$this->app->bound(ExceptionHandler::class)) {
            return;
        }

        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    $this->app->make(ErrorNotifierManager::class)->notify($e);
                });
            }
        } catch (Throwable) {
            // Ignore during early bootstrap if ExceptionHandler cannot be resolved
        }
    }
}
