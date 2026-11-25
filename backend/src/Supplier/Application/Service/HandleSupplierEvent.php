<?php

declare(strict_types=1);

namespace App\Supplier\Application\Service;

use App\Observability\MetricsRegistry;
use App\Supplier\Application\Port\SupplierEventDeduplication;
use App\Supplier\Application\Port\SupplierListCache;
use App\Supplier\Application\Port\SupplierSearchIndex;
use App\Supplier\Domain\Repository\SupplierRepository;
use RuntimeException;
use Throwable;

final readonly class HandleSupplierEvent
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierSearchIndex $searchIndex,
        private SupplierListCache $cache,
        private SupplierEventDeduplication $deduplication,
        private MetricsRegistry $metrics,
    ) {
    }

    /** @param array<string, mixed> $event */
    public function __invoke(array $event): void
    {
        $eventId = (string) $event['eventId'];
        $type = (string) $event['eventType'];
        $supplierId = (int) $event['aggregateId'];
        $claimToken = $this->deduplication->claim($eventId, $type, $supplierId);

        if ($claimToken === null) {
            $this->metrics->incrementSupplierEvent('deduplicated');

            return;
        }

        try {
            if ($type === 'SupplierDeleted') {
                $this->searchIndex->delete($supplierId);
                $this->cache->invalidate();
            } elseif (in_array($type, [
                'SupplierCreated', 'SupplierUpdated',
                'SupplierOnboardingSubmitted', 'SupplierOnboardingApproved',
                'SupplierOnboardingRejected', 'SupplierOnboardingChangesRequested', 'SupplierClassificationUpdated',
            ], true)) {
                // Always reload the aggregate. A delayed event must not restore
                // stale payload data or recreate a supplier deleted in Oracle.
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

            // Persist completion only after every read-model side effect succeeds.
            $this->deduplication->markProcessed($eventId, $claimToken);
        } catch (Throwable $exception) {
            try {
                $this->deduplication->release($eventId, $claimToken, $exception::class);
            } catch (Throwable) {
                // Keep the original processing failure. The claim lease will
                // expire and make the event recoverable if release also fails.
            }

            throw $exception;
        }

        $this->metrics->incrementSupplierEvent('consumed');
    }
}
