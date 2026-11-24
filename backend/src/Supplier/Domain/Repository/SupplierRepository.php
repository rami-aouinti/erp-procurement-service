<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Repository;

use App\Supplier\Domain\Model\Supplier;

interface SupplierRepository
{
    /** @return list<Supplier> */
    public function findAll(): array;
    public function findById(int $id): ?Supplier;
    public function save(Supplier $supplier): Supplier;
    public function delete(Supplier $supplier): void;
    public function emailExists(string $email, ?int $excludingId = null): bool;
}
