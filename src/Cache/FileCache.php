<?php

declare(strict_types=1);

namespace LicenseOS\Cache;

/**
 * File-based cache adapter.
 *
 * Stores cache data in JSON files. Suitable for single-server deployments
 * or development environments.
 */
class FileCache implements CacheInterface
{
    private string $directory;
    private string $prefix;

    /**
     * @param string $directory Directory to store cache files
     * @param string $prefix Prefix for cache files
     */
    public function __construct(string $directory, string $prefix = 'licenseos')
    {
        $this->directory = rtrim($directory, '/\\');
        $this->prefix = $prefix;

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null) {
            return null;
        }

        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        $path = $this->getPath($key);

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
        ];

        $result = file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return $result !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    private function getPath(string $key): string
    {
        // Sanitize key for filesystem
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);

        return $this->directory . '/' . $this->prefix . '_' . $safeKey . '.json';
    }
}
