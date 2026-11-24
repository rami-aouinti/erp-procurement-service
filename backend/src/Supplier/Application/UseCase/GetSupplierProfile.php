<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierProfile;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class GetSupplierProfile
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierProfileRepository $profiles,
    ) {
    }

    public function execute(int $supplierId): SupplierProfile
    {
        if ($this->suppliers->findById($supplierId) === null) {
            throw SupplierNotFound::withId($supplierId);
        }

        return $this->profiles->findBySupplierId($supplierId)
            ?? new SupplierProfile($supplierId, null, null, null, null);
    }
}
