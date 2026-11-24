<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierEmail
{
    private function __construct(public string $value) {}

    public static function fromNullable(?string $value): ?self
    {
        $value = $value !== null ? mb_strtolower(trim($value)) : null;
        if ($value === '') return null;
        if ($value !== null && (mb_strlen($value) > 150 || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidSupplierData('Supplier email must be valid and must not exceed 150 characters.');
        }
        return $value === null ? null : new self($value);
    }
}
