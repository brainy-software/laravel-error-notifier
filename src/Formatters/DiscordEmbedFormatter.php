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

        /** @var array<string, mixed> $messageConfig */
        $messageConfig = is_array($config['message'] ?? null) ? $config['message'] : [];
        /** @var array<string, bool> $fieldsConfig */
        $fieldsConfig = is_array($messageConfig['fields'] ?? null) ? $messageConfig['fields'] : [];
        $maxTraceLines = isset($config['max_trace_lines']) ? (int) $config['max_trace_lines'] : $this->maxTraceLines;

        $fields = [];

        if ($fieldsConfig['file'] ?? true) {
            $fields[] = [
                'name' => '📁 File',
                'value' => "`{$file}`",
                'inline' => false,
            ];
        }

        if ($fieldsConfig['environment'] ?? true) {
            $fields[] = [
                'name' => '🌍 Environment',
                'value' => "`{$env}`",
                'inline' => true,
            ];
        }

        if (($fieldsConfig['url'] ?? true) && !empty($context['url'])) {
            $method = $context['method'] ?? 'GET';
            $fields[] = [
                'name' => '🔗 Request',
                'value' => "`{$method} {$context['url']}`",
                'inline' => true,
            ];
        }

        if (($fieldsConfig['ip'] ?? true) && !empty($context['ip'])) {
            $fields[] = [
                'name' => '🖥️ Client IP',
                'value' => "`{$context['ip']}`",
                'inline' => true,
            ];
        }

        if (($fieldsConfig['user'] ?? true) && !empty($context['user'])) {
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
        if (($fieldsConfig['payload'] ?? true) && !empty($context['payload']) && is_array($context['payload'])) {
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
        if ($fieldsConfig['trace'] ?? true) {
            $trace = $this->extractAppTrace($exception, $maxTraceLines);
            if (!empty($trace)) {
                $fields[] = [
                    'name' => '📜 Stack Trace (Application Code)',
                    'value' => "```\n" . $this->truncate($trace, 1000) . "\n```",
                    'inline' => false,
                ];
            }
        }

        // Title customization
        $titleTemplate = $messageConfig['title'] ?? null;
        if (!empty($titleTemplate) && is_string($titleTemplate)) {
            $title = str_replace(
                ['{environment}', '{class}', '{message}'],
                [$env, $className, $message],
                $titleTemplate
            );
        } else {
            $title = "🚨 [{$env}] {$className}";
        }

        $embed = [
            'title' => $this->truncate($title, 256),
            'description' => "```\n" . $this->truncate($message, 500) . "\n```",
            'color' => 0xE02424, // Red
            'fields' => $fields,
            'timestamp' => date('c'),
            'footer' => [
                'text' => 'Powered by brainy/laravel-error-notifier',
            ],
        ];

        $payload = [
            'embeds' => [$embed],
        ];

        // Discord Mention (sent in content to trigger push notifications)
        if (!empty($config['mention'])) {
            $payload['content'] = (string) $config['mention'];
        }

        if (!empty($config['username'])) {
            $payload['username'] = $config['username'];
        }
        if (!empty($config['avatar_url'])) {
            $payload['avatar_url'] = $config['avatar_url'];
        }

        return $payload;
    }

    protected function extractAppTrace(Throwable $exception, ?int $limit = null): string
    {
        $max = $limit ?? $this->maxTraceLines;
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

            if (count($lines) >= $max) {
                break;
            }
        }

        if (empty($lines)) {
            foreach (array_slice($exception->getTrace(), 0, $max) as $index => $frame) {
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
