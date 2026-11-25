<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierAddress;
use App\Supplier\Domain\Model\SupplierContact;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class ManageSupplierProfileRelations
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierProfileRepository $profiles,
    ) {
    }

    /** @return list<SupplierContact> */
    public function listContacts(int $supplierId): array
    {
        $this->assertSupplierExists($supplierId);

        return $this->profiles->findContacts($supplierId);
    }

    public function addContact(SupplierContact $contact): SupplierContact
    {
        $this->assertSupplierExists($contact->supplierId);

        return $this->profiles->addContact($contact);
    }

    public function updateContact(SupplierContact $contact): bool
    {
        $this->assertSupplierExists($contact->supplierId);
        if ($contact->id === null) {
            return false;
        }
        return $this->profiles->updateContact($contact);
    }

    public function deleteContact(int $supplierId, int $contactId): bool
    {
        $this->assertSupplierExists($supplierId);
        return $this->profiles->deleteContact($supplierId, $contactId);
    }

    /** @return list<SupplierAddress> */
    public function listAddresses(int $supplierId): array
    {
        $this->assertSupplierExists($supplierId);

        return $this->profiles->findAddresses($supplierId);
    }

    public function addAddress(SupplierAddress $address): SupplierAddress
    {
        $this->assertSupplierExists($address->supplierId);

        return $this->profiles->addAddress($address);
    }

    public function updateAddress(SupplierAddress $address): bool
    {
        $this->assertSupplierExists($address->supplierId);
        if ($address->id === null) {
            return false;
        }

        return $this->profiles->updateAddress($address);
    }

    public function deleteAddress(int $supplierId, int $addressId): bool
    {
        $this->assertSupplierExists($supplierId);

        return $this->profiles->deleteAddress($supplierId, $addressId);
    }

    private function assertSupplierExists(int $supplierId): void
    {
        if ($this->suppliers->findById($supplierId) === null) {
            throw SupplierNotFound::withId($supplierId);
        }
    }
}
