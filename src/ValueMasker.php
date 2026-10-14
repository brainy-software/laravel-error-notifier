<?php

namespace Brainy\ErrorNotifier;

class ValueMasker
{
    /**
     * @var list<string>
     */
    protected array $maskKeys;

    /**
     * @param list<string> $maskKeys
     */
    public function __construct(array $maskKeys = [])
    {
        $this->maskKeys = array_map('strtolower', $maskKeys);
    }

    /**
     * Mask sensitive values in an array or scalar recursively.
     *
     * @param mixed $data
     * @return mixed
     */
    public function mask(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = strtolower($key);
                if ($this->shouldMaskKey($normalizedKey)) {
                    $result[$key] = '********';
                    continue;
                }
            }

            if (is_array($value)) {
                $result[$key] = $this->mask($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Determine if a given key name matches any sensitive mask pattern.
     */
    protected function shouldMaskKey(string $key): bool
    {
        foreach ($this->maskKeys as $pattern) {
            if ($key === $pattern || str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
