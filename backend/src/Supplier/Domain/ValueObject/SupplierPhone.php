<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierPhone
{
    private function __construct(public string $value) {}

    public static function fromNullable(?string $value): ?self
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === '') return null;
        if ($value !== null && mb_strlen($value) > 30) {
            throw new InvalidSupplierData('Supplier phone must not exceed 30 characters.');
        }
        return $value === null ? null : new self($value);
    }
}
