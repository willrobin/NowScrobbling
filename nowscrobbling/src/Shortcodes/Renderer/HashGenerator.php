<?php

declare(strict_types=1);

namespace NowScrobbling\Shortcodes\Renderer;

/**
 * Hash Generator
 *
 * Generates content hashes for client-side diffing.
 * Only updates DOM when content actually changes.
 *
 * @package NowScrobbling\Shortcodes\Renderer
 */
final class HashGenerator
{
    /**
     * Generate a hash from data
     *
     * @param mixed $data Data to hash
     */
    public function generate(mixed $data): string
    {
        if ($data === null) {
            return md5('null');
        }

        if (is_scalar($data)) {
            return md5((string) $data);
        }

        if (is_array($data)) {
            return $this->hashArray($data);
        }

        if (is_object($data)) {
            return md5(serialize($data));
        }

        return md5(gettype($data));
    }

    /**
     * Hash an array, removing volatile keys
     *
     * @param array<string, mixed> $data Array to hash
     */
    private function hashArray(array $data): string
    {
        // Remove keys that change but don't affect display
        $volatileKeys = [
            '@attr',
            'date',
            'uts',
            'timestamp',
            'cached_at',
            '__ns_cached',
        ];

        $cleaned = $this->removeVolatileKeys($data, $volatileKeys);

        return md5(json_encode($cleaned, JSON_THROW_ON_ERROR) ?: '');
    }

    /**
     * Recursively remove volatile keys from array
     *
     * @param array<string, mixed> $data         Array to clean
     * @param array<string>        $volatileKeys Keys to remove
     *
     * @return array<string, mixed>
     */
    private function removeVolatileKeys(array $data, array $volatileKeys): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip volatile keys
            if (in_array($key, $volatileKeys, true)) {
                continue;
            }

            // Recurse into nested arrays
            if (is_array($value)) {
                $result[$key] = $this->removeVolatileKeys($value, $volatileKeys);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if two hashes match
     *
     * @param string $hash1 First hash
     * @param string $hash2 Second hash
     */
    public function matches(string $hash1, string $hash2): bool
    {
        return hash_equals($hash1, $hash2);
    }
}
