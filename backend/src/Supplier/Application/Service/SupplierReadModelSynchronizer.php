<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierListCache;

/**
 * Cache invalidation is intentionally separate from Elasticsearch projection updates.
 * Elasticsearch is updated only by the transactional-outbox consumer.
 */
final readonly class SupplierReadModelSynchronizer
{
    public function __construct(private SupplierListCache $cache) {}

    public function invalidate(): void
    {
        $this->cache->invalidate();
    }
}
