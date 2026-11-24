<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierName
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 150) {
            throw new InvalidSupplierData('Supplier name is required and must not exceed 150 characters.');
        }
        return new self($value);
    }
}
