<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierEventDeduplication
{
    /**
     * Claims an event for processing.
     *
     * Returns a claim token, or null when the event was already processed.
     * Throws when another live claim is currently processing the event.
     */
    public function claim(string $eventId, string $eventType, int $aggregateId): ?string;

    public function markProcessed(string $eventId, string $claimToken): void;

    public function release(string $eventId, string $claimToken, string $errorClass): void;
}
