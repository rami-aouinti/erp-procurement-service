<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierListCache
{
    /** @return list<array<string, mixed>>|null */
    public function get(string $key): ?array;

    /** @param list<array<string, mixed>> $suppliers */
    public function set(string $key, array $suppliers, int $ttl = 60): void;

    public function invalidate(): void;
}
