<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierTaxNumber
{
    private function __construct(public string $value) {}

    public static function fromNullable(?string $value): ?self
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === '') return null;
        if ($value !== null && mb_strlen($value) > 50) {
            throw new InvalidSupplierData('Supplier tax number must not exceed 50 characters.');
        }
        return $value === null ? null : new self($value);
    }
}
