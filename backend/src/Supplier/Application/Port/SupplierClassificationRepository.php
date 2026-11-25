<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierClassificationRepository
{
    /** @return list<array<string,mixed>> */
    public function list(string $type): array;

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(string $type, array $data): array;

    /** @return array<string,mixed> */
    public function setActive(string $type, int $id, bool $active): array;

    /** @return array<string,mixed> */
    public function get(int $supplierId): array;

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function assign(int $supplierId, array $data, string $actor): array;
}
