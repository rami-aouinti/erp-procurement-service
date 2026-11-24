<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Service\SupplierReadModelSynchronizer;
use App\Supplier\Application\Validator\SupplierDataValidator;
use App\Supplier\Domain\Exception\SupplierEmailAlreadyUsed;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class UpdateSupplier
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierDataValidator $validator,
        private SupplierReadModelSynchronizer $readModel,
    ) {
    }

    public function execute(int $id, SupplierData $data): Supplier
    {
        $this->validator->validate($data);
        $supplier = $this->suppliers->findById($id) ?? throw SupplierNotFound::withId($id);
        if ($data->email !== null && $this->suppliers->emailExists(mb_strtolower(trim($data->email)), $id)) {
            throw new SupplierEmailAlreadyUsed('A supplier with this email already exists.');
        }

        $supplier->update(
            $data->name, $data->email, $data->phone, $data->taxNumber,
            $data->status, $data->contactPerson, $data->street,
            $data->city, $data->postalCode, $data->country,
        );

        $supplier = $this->suppliers->save($supplier);
        $this->readModel->upsert($supplier);

        return $supplier;
    }
}
