<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Cache;

use App\Supplier\Application\Port\SupplierListCache;
use Redis;
use RedisException;
use Throwable;

final class RedisSupplierListCache implements SupplierListCache
{
    private const PREFIX = 'erp:suppliers:';
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
            return null;
        }
    }

    public function set(string $key, array $suppliers, int $ttl = 60): void
    {
        try {
            $this->connection()->setex(
                self::PREFIX . $key,
                max(1, $ttl),
                json_encode($suppliers, JSON_THROW_ON_ERROR),
            );
        } catch (Throwable) {
            // Redis is an optimization; Oracle remains available when cache is down.
        }
    }

    public function invalidate(): void
    {
        try {
            $redis = $this->connection();
            $iterator = null;
            do {
                $keys = $redis->scan($iterator, self::PREFIX . '*', 100);
                if (is_array($keys) && $keys !== []) {
                    $redis->del($keys);
                }
            } while ($iterator !== 0 && $iterator !== false);
        } catch (Throwable) {
            // Expiring keys will self-heal if Redis is temporarily unavailable.
        }
    }

    private function connection(): Redis
    {
        if ($this->redis instanceof Redis && $this->redis->isConnected()) {
            return $this->redis;
        }

        $redis = new Redis();
        if (!$redis->connect($this->host, $this->port, 1.5)) {
            throw new RedisException('Unable to connect to Redis.');
        }
        $redis->setOption(Redis::OPT_READ_TIMEOUT, 1.5);
        $this->redis = $redis;

        return $redis;
    }
}
