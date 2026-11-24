<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Event;

interface SupplierDomainEvent
{
    public function eventType(): string;
    public function aggregateId(): int;
    /** @return array<string, mixed> */
    public function payload(): array;
}
