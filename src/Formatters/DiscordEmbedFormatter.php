<?php

namespace Brainy\ErrorNotifier\Formatters;

use Throwable;

class DiscordEmbedFormatter
{
    protected int $maxTraceLines;

    public function __construct(int $maxTraceLines = 12)
    {
        $this->maxTraceLines = $maxTraceLines;
    }

    /**
     * Format exception and context into Discord Webhook payload with Embeds.
     *
     * @param Throwable $exception
     * @param array<string, mixed> $context
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function format(Throwable $exception, array $context, array $config = []): array
    {
        $className = class_basename($exception);
        $message = $exception->getMessage() ?: '(No message)';
        $file = $this->formatFilePath($exception->getFile()) . ':' . $exception->getLine();
        $env = strtoupper((string) ($context['environment'] ?? 'UNKNOWN'));

        $fields = [
            [
                'name' => '📁 File',
                'value' => "`{$file}`",
                'inline' => false,
            ],
            [
                'name' => '🌍 Environment',
                'value' => "`{$env}`",
                'inline' => true,
            ],
        ];

        if (!empty($context['url'])) {
            $method = $context['method'] ?? 'GET';
            $fields[] = [
                'name' => '🔗 Request',
                'value' => "`{$method} {$context['url']}`",
                'inline' => true,
            ];
        }

        if (!empty($context['ip'])) {
            $fields[] = [
                'name' => '🖥️ Client IP',
                'value' => "`{$context['ip']}`",
                'inline' => true,
            ];
        }

        if (!empty($context['user'])) {
            $userInfo = is_array($context['user'])
                ? ($context['user']['id'] ?? json_encode($context['user']))
                : (string) $context['user'];
            $fields[] = [
                'name' => '👤 User',
                'value' => "`{$userInfo}`",
                'inline' => true,
            ];
        }

        // Request Payload
        if (!empty($context['payload']) && is_array($context['payload'])) {
            $jsonPayload = json_encode($context['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonPayload && $jsonPayload !== '[]' && $jsonPayload !== '{}') {
                $fields[] = [
                    'name' => '📦 Request Data (Sanitized)',
                    'value' => "```json\n" . $this->truncate($jsonPayload, 800) . "\n```",
                    'inline' => false,
                ];
            }
        }

        // Stack Trace
        $trace = $this->extractAppTrace($exception);
        if (!empty($trace)) {
            $fields[] = [
                'name' => '📜 Stack Trace (Application Code)',
                'value' => "```\n" . $this->truncate($trace, 1000) . "\n```",
                'inline' => false,
            ];
        }

        $embed = [
            'title' => "🚨 [{$env}] {$className}",
            'description' => "```\n" . $this->truncate($message, 500) . "\n```",
            'color' => 0xE02424, // Red
            'fields' => $fields,
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'Powered by B-Beacon & brainy/laravel-error-notifier',
                'icon_url' => 'https://b-beacon.biz/favicon.ico',
            ],
        ];

        $payload = [
            'embeds' => [$embed],
        ];

        if (!empty($config['username'])) {
            $payload['username'] = $config['username'];
        }
        if (!empty($config['avatar_url'])) {
            $payload['avatar_url'] = $config['avatar_url'];
        }

        return $payload;
    }

    protected function extractAppTrace(Throwable $exception): string
    {
        $lines = [];
        $basePath = defined('base_path') ? base_path() : getcwd();

        foreach ($exception->getTrace() as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];

            $relFile = $file;
            if ($basePath && str_starts_with($file, $basePath)) {
                $relFile = ltrim(substr($file, strlen($basePath)), DIRECTORY_SEPARATOR);
            }

            if (str_starts_with($relFile, 'vendor/')) {
                continue;
            }

            $lines[] = "#{$index} {$relFile}:{$line} {$func}()";

            if (count($lines) >= $this->maxTraceLines) {
                break;
            }
        }

        if (empty($lines)) {
            foreach (array_slice($exception->getTrace(), 0, $this->maxTraceLines) as $index => $frame) {
                $file = $this->formatFilePath($frame['file'] ?? '[internal]');
                $line = $frame['line'] ?? 0;
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
                $lines[] = "#{$index} {$file}:{$line} {$func}()";
            }
        }

        return implode("\n", $lines);
    }

    protected function formatFilePath(string $path): string
    {
        $basePath = defined('base_path') ? base_path() : getcwd();
        if ($basePath && str_starts_with($path, $basePath)) {
            return ltrim(substr($path, strlen($basePath)), DIRECTORY_SEPARATOR);
        }
        return $path;
    }

    protected function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3) . '...';
    }
}
