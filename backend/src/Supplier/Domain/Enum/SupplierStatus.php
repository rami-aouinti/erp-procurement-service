<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Enum;

use App\Supplier\Domain\Exception\InvalidSupplierData;

enum SupplierStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case BLOCKED = 'BLOCKED';

    public static function fromInput(string $status): self
    {
        return self::tryFrom(strtoupper(trim($status)))
            ?? throw new InvalidSupplierData('Supplier status must be ACTIVE, INACTIVE or BLOCKED.');
    }
}
