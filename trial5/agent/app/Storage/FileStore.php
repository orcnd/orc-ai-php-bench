<?php
declare(strict_types=1);
namespace App\Storage;

/**
 * Shared document store (platform team). In production the directory is an
 * NFS volume mounted by every PHP-FPM worker on every web node.
 *
 * - get()/put() are atomic per document (write to temp file + rename) but
 *   two workers doing get -> modify -> put can overwrite each other.
 * - transaction() holds an exclusive cross-process lock (flock) on a named
 *   lock for the duration of the callback. Locks are NOT re-entrant: do not
 *   open the same lock name again inside its own callback.
 * - STORE_LATENCY_US simulates network-storage latency in load tests.
 */
final class FileStore
{
    private string $directory;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create store ' . $directory);
        }
        $this->directory = rtrim($directory, '/');
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->latency();
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read ' . $key);
        }
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param mixed $value */
    public function put(string $key, $value): void
    {
        $this->latency();
        $file = $this->path($key);
        $temporary = $file . '.' . getmypid() . '.' . mt_rand() . '.tmp';
        file_put_contents($temporary, json_encode($value, JSON_THROW_ON_ERROR));
        rename($temporary, $file);
    }

    /**
     * @template T
     * @param callable(): T $body
     * @return T
     */
    public function transaction(string $lockName, callable $body)
    {
        $handle = fopen($this->path($lockName) . '.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock ' . $lockName);
        }
        try {
            return $body();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . rawurlencode($key) . '.json';
    }

    private function latency(): void
    {
        $microseconds = (int) getenv('STORE_LATENCY_US');
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }
}
