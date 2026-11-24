<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Model\Supplier;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SupplierReadModelSynchronizer
{
    public function __construct(
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function upsert(Supplier $supplier): void
    {
        $this->cache->invalidate();
        try {
            $this->searchIndex->index($supplier);
        } catch (Throwable $exception) {
            $this->logger->error('Supplier saved in Oracle but Elasticsearch indexing failed. Run supplier:reindex.', [
                'supplierId' => $supplier->id(),
                'exception' => $exception,
            ]);
        }
    }

    public function remove(int $supplierId): void
    {
        $this->cache->invalidate();
        try {
            $this->searchIndex->delete($supplierId);
        } catch (Throwable $exception) {
            $this->logger->error('Supplier deleted in Oracle but Elasticsearch removal failed. Run supplier:reindex.', [
                'supplierId' => $supplierId,
                'exception' => $exception,
            ]);
        }
    }
}
