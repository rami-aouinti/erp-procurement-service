<?php

declare(strict_types=1);

namespace App\Tests\Configuration\Infrastructure;

use App\Configuration\Infrastructure\Cache\RedisConfigurationCache;
use PHPUnit\Framework\TestCase;

final class RedisConfigurationCacheTest extends TestCase
{
    public function testCacheFailureIsNonFatalAndFallsBackToTheCaller(): void
    {
        // Port 1 is expected to refuse connections immediately in the test container.
        $cache = new RedisConfigurationCache('127.0.0.1', 1);

        self::assertNull($cache->get('site'));

        // Cache writes and invalidation are best-effort: a Redis outage must not
        // turn a successful Oracle-backed configuration update into an API error.
        $cache->set('site', ['siteName' => 'Fallback ERP']);
        $cache->invalidate('site');

        self::assertTrue(true);
    }
}
