<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\Service\SupplierReadModelSynchronizer;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class DeleteSupplier
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierReadModelSynchronizer $readModel,
    ) {
    }

    public function execute(int $id): void
    {
        $supplier = $this->suppliers->findById($id) ?? throw SupplierNotFound::withId($id);
        $this->suppliers->delete($supplier);
        $this->readModel->remove($id);
    }
}
