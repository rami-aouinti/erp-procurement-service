<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

use App\Supplier\Domain\Model\Supplier;

interface SupplierSearchIndex
{
    /** @return list<array<string, mixed>> */
    public function search(string $query, ?string $status = null, int $limit = 500): array;

    public function index(Supplier $supplier): void;

    public function delete(int $supplierId): void;

    /** @param iterable<Supplier> $suppliers */
    public function reindex(iterable $suppliers): int;
}
