<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierEventDeduplication
{
    /**
     * Claims an event for processing.
     *
     * Returns a claim token when this worker owns the event.
     * Returns null when it is already processed or another live delivery owns it.
     * Expired claims and previously released failures can be reclaimed.
     */
    public function claim(string $eventId, string $eventType, int $aggregateId): ?string;

    public function markProcessed(string $eventId, string $claimToken): void;

    public function release(string $eventId, string $claimToken, string $errorClass): void;
}
