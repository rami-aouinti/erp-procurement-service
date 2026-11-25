<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierClassificationReader
{
    /** @return array<string, mixed> */
    public function forSupplier(int $supplierId): array;

    /** @param array<string, mixed> $filters @return list<int> */
    public function matchingSupplierIds(array $filters): array;
}
