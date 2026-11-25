<?php

declare(strict_types=1);

namespace App\Configuration\Infrastructure\Cache;

use App\Configuration\Application\Port\ConfigurationCache;
use Redis;
use Throwable;

final class RedisConfigurationCache implements ConfigurationCache
{
    private const PREFIX = 'erp:configuration:';
    private ?Redis $redis = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
    ) {
    }

    public function get(string $key): ?array
    {
        try {
            $value = $this->connection()->get(self::PREFIX . $key);
            if (!is_string($value)) {
                return null;
            }
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            $this->redis = null;

            return null;
        }
    }

    public function set(string $key, array $value, int $ttl = 300): void
    {
        try {
            $this->connection()->setex(
                self::PREFIX . $key,
                max(1, $ttl),
                json_encode($value, JSON_THROW_ON_ERROR),
            );
        } catch (Throwable) {
            $this->redis = null;
            // Cache failures must never make the configuration API unavailable.
        }
    }

    public function invalidate(string $key): void
    {
        try {
            $this->connection()->del(self::PREFIX . $key);
        } catch (Throwable) {
            $this->redis = null;
        }
    }

    private function connection(): Redis
    {
        if ($this->redis instanceof Redis && $this->redis->isConnected()) {
            return $this->redis;
        }

        $redis = new Redis();
        if (!$redis->connect($this->host, $this->port, 1.5)) {
            throw new \RuntimeException('Unable to connect to Redis.');
        }
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.5);
        $this->redis = $redis;

        return $redis;
    }
}
