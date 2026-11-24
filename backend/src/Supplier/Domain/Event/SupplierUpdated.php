<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Event;

use App\Supplier\Domain\Model\Supplier;

final readonly class SupplierUpdated implements SupplierDomainEvent
{
    public function __construct(private Supplier $supplier) {}
    public function eventType(): string { return 'SupplierUpdated'; }
    public function aggregateId(): int { return $this->supplier->id() ?? throw new \LogicException('A persisted supplier is required.'); }
    public function payload(): array { return SupplierEventPayload::fromSupplier($this->supplier); }
}
