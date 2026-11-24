<?php

declare(strict_types=1);

namespace App\Supplier\Application\Validator;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Exception\SupplierValidationException;

final class SupplierDataValidator
{
    public function validate(SupplierData $data): void
    {
        $violations = [];

        $name = trim($data->name);
        if ($name === '') {
            $violations[] = 'Supplier name is required.';
        } elseif ($this->length($name) > 150) {
            $violations[] = 'Supplier name must not exceed 150 characters.';
        }

        $email = $this->nullableTrim($data->email);
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $violations[] = 'Supplier email must be a valid email address.';
            }
            if ($this->length($email) > 150) {
                $violations[] = 'Supplier email must not exceed 150 characters.';
            }
        }

        $phone = $this->nullableTrim($data->phone);
        if ($phone !== null && $this->length($phone) > 30) {
            $violations[] = 'Supplier phone must not exceed 30 characters.';
        }

        $taxNumber = $this->nullableTrim($data->taxNumber);
        if ($taxNumber !== null && $this->length($taxNumber) > 50) {
            $violations[] = 'Supplier tax number must not exceed 50 characters.';
        }

        if ($violations !== []) {
            throw new SupplierValidationException($violations);
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function length(string $value): int
    {
        preg_match_all('/./us', $value, $matches);

        return count($matches[0]);
    }
}
