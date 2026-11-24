<?php

namespace App\Service;

use App\DTO\Supplier\SupplierInput;
use App\Entity\Supplier;
use App\Repository\SupplierRepository;

final readonly class SupplierService
{
    public function __construct(private SupplierRepository $suppliers)
    {
    }

    /** @return list<Supplier> */
    public function list(): array
    {
        return $this->suppliers->findAllOrderedByName();
    }

    public function get(int $id): ?Supplier
    {
        return $this->suppliers->find($id);
    }

    public function create(SupplierInput $input): Supplier
    {
        $supplier = new Supplier($input->name, $input->email, $input->phone, $input->taxNumber);
        $this->suppliers->save($supplier);

        return $supplier;
    }

    public function update(Supplier $supplier, SupplierInput $input): Supplier
    {
        $supplier->rename($input->name);
        $supplier->changeContactDetails($input->email, $input->phone, $input->taxNumber);
        $this->suppliers->save($supplier);

        return $supplier;
    }

    public function delete(Supplier $supplier): void
    {
        $this->suppliers->remove($supplier);
    }
}
