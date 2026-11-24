<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Observability\MetricsRegistry;
use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use RuntimeException;

final readonly class HandleSupplierEvent
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
        private MetricsRegistry $metrics,
    ) {
    }

    /** @param array<string, mixed> $event */
    public function __invoke(array $event): void
    {
        $type = (string) $event['eventType'];
        $supplierId = (int) $event['aggregateId'];

        if ($type === 'SupplierDeleted') {
            $this->searchIndex->delete($supplierId);
            $this->cache->invalidate();
        } elseif (in_array($type, ['SupplierCreated', 'SupplierUpdated'], true)) {
            // Always reload the aggregate. A delayed duplicate must not restore
            // stale payload data or recreate a supplier that was deleted later.
            $supplier = $this->suppliers->findById($supplierId);
            if ($supplier === null) {
                $this->searchIndex->delete($supplierId);
            } else {
                $this->searchIndex->index($supplier);
            }
            $this->cache->invalidate();
        } else {
            throw new RuntimeException(sprintf('Unsupported supplier event type "%s".', $type));
        }

        // The consumer acknowledges only after this handler returns.
        $this->metrics->incrementSupplierEvent('consumed');
    }
}
