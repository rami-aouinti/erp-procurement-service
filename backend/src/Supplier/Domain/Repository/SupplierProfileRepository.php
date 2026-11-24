<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Repository;

use App\Supplier\Domain\Model\SupplierProfile;

interface SupplierProfileRepository
{
    public function findBySupplierId(int $supplierId): ?SupplierProfile;

    public function save(SupplierProfile $profile): SupplierProfile;
}
