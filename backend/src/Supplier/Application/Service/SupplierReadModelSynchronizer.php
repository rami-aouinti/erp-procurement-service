<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Model\Supplier;
use Throwable;

final readonly class SupplierReadModelSynchronizer
{
    public function __construct(
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
    ) {
    }

    public function upsert(Supplier $supplier): void
    {
        $this->cache->invalidate();
        try {
            $this->searchIndex->index($supplier);
        } catch (Throwable $exception) {
            error_log(sprintf('Supplier %s was saved in Oracle but immediate Elasticsearch indexing failed: %s. The transactional outbox will retry.', $supplier->id(), $exception->getMessage()));
        }
    }

    public function remove(int $supplierId): void
    {
        $this->cache->invalidate();
        try {
            $this->searchIndex->delete($supplierId);
        } catch (Throwable $exception) {
            error_log(sprintf('Supplier %d was deleted in Oracle but immediate Elasticsearch removal failed: %s. The transactional outbox will retry.', $supplierId, $exception->getMessage()));
        }
    }
}
