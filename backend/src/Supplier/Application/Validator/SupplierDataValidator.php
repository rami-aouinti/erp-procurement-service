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
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $violations[] = 'Supplier email must be a valid email address.';
        }
        if ($email !== null && $this->length($email) > 150) $violations[] = 'Supplier email must not exceed 150 characters.';
        $this->maxLength($data->phone, 30, 'Supplier phone', $violations);
        $this->maxLength($data->taxNumber, 50, 'Supplier tax number', $violations);
        $this->maxLength($data->contactPerson, 150, 'Contact person', $violations);
        $this->maxLength($data->street, 180, 'Street', $violations);
        $this->maxLength($data->city, 100, 'City', $violations);
        $this->maxLength($data->postalCode, 20, 'Postal code', $violations);
        $this->maxLength($data->country, 2, 'Country code', $violations);

        if (!in_array(strtoupper(trim($data->status)), ['ACTIVE', 'INACTIVE', 'BLOCKED'], true)) {
            $violations[] = 'Supplier status must be ACTIVE, INACTIVE or BLOCKED.';
        }
        if ($data->country !== null && trim($data->country) !== '' && !preg_match('/^[A-Za-z]{2}$/', trim($data->country))) {
            $violations[] = 'Country must be a two-letter ISO country code.';
        }
        if ($violations !== []) throw new SupplierValidationException($violations);
    }

    /** @param list<string> $violations */
    private function maxLength(?string $value, int $max, string $label, array &$violations): void
    {
        $value = $this->nullableTrim($value);
        if ($value !== null && $this->length($value) > $max) $violations[] = sprintf('%s must not exceed %d characters.', $label, $max);
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
