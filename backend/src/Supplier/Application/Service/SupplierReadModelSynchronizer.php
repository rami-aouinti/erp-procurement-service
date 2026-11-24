<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Domain\Model\Supplier;

final readonly class SupplierReadModelSynchronizer
{
    public function __construct(private SupplierListCache $cache)
    {
    }

    /**
     * Oracle + transactional outbox are committed first. Elasticsearch is updated
     * asynchronously by the RabbitMQ consumer; only invalidate Redis here.
     */
    public function upsert(Supplier $supplier): void
    {
        $this->cache->invalidate();
    }

    public function remove(int $supplierId): void
    {
        $this->cache->invalidate();
    }
}
