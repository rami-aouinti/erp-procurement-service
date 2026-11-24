<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class ListSuppliers
{
    public function __construct(private SupplierRepository $suppliers)
    {
    }
    /** @return list<Supplier> */
    public function execute(): array
    {
        return $this->suppliers->findAll();
    }
}
