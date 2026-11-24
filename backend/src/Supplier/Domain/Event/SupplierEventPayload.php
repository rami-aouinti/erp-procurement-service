<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Event;

use App\Supplier\Domain\Model\Supplier;

final class SupplierEventPayload
{
    /** @return array<string, mixed> */
    public static function fromSupplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id(),
            'name' => $supplier->name(),
            'email' => $supplier->email(),
            'phone' => $supplier->phone(),
            'taxNumber' => $supplier->taxNumber(),
            'status' => $supplier->status(),
            'contactPerson' => $supplier->contactPerson(),
            'street' => $supplier->street(),
            'city' => $supplier->city(),
            'postalCode' => $supplier->postalCode(),
            'country' => $supplier->country(),
            'createdAt' => $supplier->createdAt()->format(DATE_ATOM),
        ];
    }
}
