<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierEmail
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        $value = mb_strtolower(trim($value));
        if (mb_strlen($value) > 150 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidSupplierData('Supplier email must be valid.');
        }
        return new self($value);
    }
}
