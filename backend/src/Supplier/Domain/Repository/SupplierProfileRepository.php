<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Repository;

use App\Supplier\Domain\Model\SupplierAddress;
use App\Supplier\Domain\Model\SupplierContact;
use App\Supplier\Domain\Model\SupplierProfile;

interface SupplierProfileRepository
{
    public function findBySupplierId(int $supplierId): ?SupplierProfile;

    public function save(SupplierProfile $profile): SupplierProfile;

    /** @return list<SupplierContact> */
    public function findContacts(int $supplierId): array;

    public function addContact(SupplierContact $contact): SupplierContact;

    /** @return list<SupplierAddress> */
    public function findAddresses(int $supplierId): array;

    public function addAddress(SupplierAddress $address): SupplierAddress;
}
