<?php

declare(strict_types=1);

namespace App\Supplier\Application\EventHandler;

use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use RuntimeException;

/**
 * Handles the integration event delivered from the transactional outbox.
 * Re-reading Oracle makes duplicate and out-of-order deliveries idempotent.
 */
final readonly class SupplierProjectionEventHandler
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
    ) {
    }

    /** @param array<string, mixed> $event */
    public function __invoke(array $event): void
    {
        $type = (string) ($event['eventType'] ?? '');
        $supplierId = (int) ($event['aggregateId'] ?? 0);
        if ($supplierId <= 0) {
            throw new RuntimeException('Supplier event has no valid aggregateId.');
        }

        if ($type === 'SupplierDeleted') {
            $this->searchIndex->delete($supplierId);
            $this->cache->invalidate();
            return;
        }
        if (!in_array($type, ['SupplierCreated', 'SupplierUpdated'], true)) {
            throw new RuntimeException(sprintf('Unsupported supplier event type "%s".', $type));
        }

        $supplier = $this->suppliers->findById($supplierId);
        if ($supplier === null) $this->searchIndex->delete($supplierId);
        else $this->searchIndex->index($supplier);

        $this->cache->invalidate();
    }
}
