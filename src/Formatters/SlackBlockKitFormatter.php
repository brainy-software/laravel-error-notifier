<?php

namespace Brainy\ErrorNotifier\Formatters;

use Throwable;

class SlackBlockKitFormatter
{
    protected int $maxTraceLines;

    public function __construct(int $maxTraceLines = 12)
    {
        $this->maxTraceLines = $maxTraceLines;
    }

    /**
     * Format exception and context into Slack Block Kit payload.
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

        $blocks = [];

        // 1. Header Block
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => "🚨 [{$env}] {$className}",
                'emoji' => true,
            ],
        ];

        // 2. Exception Message
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Message:*\n```" . $this->truncate($message, 500) . "```",
            ],
        ];

        // 3. Metadata Fields Grid
        $fields = [
            [
                'type' => 'mrkdwn',
                'text' => "*File:*\n`{$file}`",
            ],
            [
                'type' => 'mrkdwn',
                'text' => "*Environment:*\n`{$env}`",
            ],
        ];

        if (!empty($context['url'])) {
            $method = $context['method'] ?? 'GET';
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Request:*\n`{$method} {$context['url']}`",
            ];
        }

        if (!empty($context['ip'])) {
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*Client IP:*\n`{$context['ip']}`",
            ];
        }

        if (!empty($context['user'])) {
            $userInfo = is_array($context['user'])
                ? ($context['user']['id'] ?? json_encode($context['user']))
                : (string) $context['user'];
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*User:*\n`{$userInfo}`",
            ];
        }

        $blocks[] = [
            'type' => 'section',
            'fields' => $fields,
        ];

        // 4. Request Payload (if present)
        if (!empty($context['payload']) && is_array($context['payload'])) {
            $jsonPayload = json_encode($context['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonPayload && $jsonPayload !== '[]' && $jsonPayload !== '{}') {
                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Request Data (Sanitized):*\n```json\n" . $this->truncate($jsonPayload, 800) . "\n```",
                    ],
                ];
            }
        }

        // 5. Stack Trace (App-focused)
        $trace = $this->extractAppTrace($exception);
        if (!empty($trace)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Stack Trace (Application Code):*\n```\n" . $this->truncate($trace, 1200) . "\n```",
                ],
            ];
        }

        // 6. Footer / Context
        $timestamp = date('Y-m-d H:i:s T');
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "🕒 {$timestamp} | Powered by <https://b-beacon.biz|*B-Beacon*> & *brainy/laravel-error-notifier*",
                ],
            ],
        ];

        $payload = ['blocks' => $blocks];

        if (!empty($config['channel'])) {
            $payload['channel'] = $config['channel'];
        }
        if (!empty($config['username'])) {
            $payload['username'] = $config['username'];
        }
        if (!empty($config['icon_emoji'])) {
            $payload['icon_emoji'] = $config['icon_emoji'];
        }

        return $payload;
    }

    /**
     * Extract stack trace focusing on application code (excluding vendor lines).
     */
    protected function extractAppTrace(Throwable $exception): string
    {
        $lines = [];
        $basePath = defined('base_path') ? base_path() : getcwd();

        foreach ($exception->getTrace() as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');

            // Relative file path
            $relFile = $file;
            if ($basePath && str_starts_with($file, $basePath)) {
                $relFile = ltrim(substr($file, strlen($basePath)), DIRECTORY_SEPARATOR);
            }

            // Exclude vendor unless we have no app lines
            if (str_starts_with($relFile, 'vendor/')) {
                continue;
            }

            $lines[] = "#{$index} {$relFile}:{$line} {$func}()";

            if (count($lines) >= $this->maxTraceLines) {
                break;
            }
        }

        // Fallback to top raw trace frames if no app-specific frames matched
        if (empty($lines)) {
            foreach (array_slice($exception->getTrace(), 0, $this->maxTraceLines) as $index => $frame) {
                $file = $this->formatFilePath($frame['file'] ?? '[internal]');
                $line = $frame['line'] ?? 0;
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
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
