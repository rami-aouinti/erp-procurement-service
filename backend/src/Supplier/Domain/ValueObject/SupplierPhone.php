<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierPhone
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        $value = trim($value);
        if (mb_strlen($value) > 30 || !preg_match('/^\+?[0-9\s().-]{7,30}$/', $value) || preg_match_all('/[0-9]/', $value) < 7) {
            throw new InvalidSupplierData('Supplier phone must contain at least 7 digits and use only common international phone characters.');
        }
        return new self($value);
    }
}
