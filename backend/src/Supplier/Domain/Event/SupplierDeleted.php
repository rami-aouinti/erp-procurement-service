<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Event;

final readonly class SupplierDeleted implements SupplierDomainEvent
{
    public function __construct(private int $supplierId) {}
    public function eventType(): string { return 'SupplierDeleted'; }
    public function aggregateId(): int { return $this->supplierId; }
    public function payload(): array { return ['id' => $this->supplierId]; }
}
