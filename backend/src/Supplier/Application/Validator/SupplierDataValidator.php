<?php

declare(strict_types=1);

namespace App\Supplier\Application\Validator;

use App\Supplier\Application\DTO\SupplierData;
use App\Supplier\Application\Exception\SupplierValidationException;

final class SupplierDataValidator
{
    private const STATUSES = ['ACTIVE', 'INACTIVE', 'BLOCKED'];

    public function validate(SupplierData $data): void
    {
        $violations = [];
        $name = trim($data->name);

        if ($name === '') {
            $violations[] = 'Supplier name is required.';
        } elseif ($this->length($name) < 2 || $this->length($name) > 150) {
            $violations[] = 'Supplier name must contain between 2 and 150 characters.';
        }
        if ($this->hasControlCharacters($name)) {
            $violations[] = 'Supplier name contains invalid control characters.';
        }

        $email = $this->nullableTrim($data->email);
        if ($email !== null) {
            if ($this->length($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $violations[] = 'Supplier email must be a valid email address of at most 150 characters.';
            }
        }

        $phone = $this->nullableTrim($data->phone);
        if ($phone !== null && (!preg_match('/^\+?[0-9\s().-]{7,30}$/', $phone) || preg_match_all('/[0-9]/', $phone) < 7)) {
            $violations[] = 'Supplier phone must contain at least 7 digits and use only common international phone characters.';
        }

        $this->maxLength($data->taxNumber, 50, 'Tax number', $violations);
        $taxNumber = $this->nullableTrim($data->taxNumber);
        if ($taxNumber !== null && !preg_match('/^[A-Za-z0-9 .\/-]+$/', $taxNumber)) {
            $violations[] = 'Tax number contains unsupported characters.';
        }

        $this->maxLength($data->contactPerson, 150, 'Contact person', $violations);
        $this->maxLength($data->street, 180, 'Street', $violations);
        $this->maxLength($data->city, 100, 'City', $violations);
        $this->maxLength($data->postalCode, 20, 'Postal code', $violations);

        foreach ([
            'Contact person' => $data->contactPerson,
            'Street' => $data->street,
            'City' => $data->city,
        ] as $label => $value) {
            if ($value !== null && $this->hasControlCharacters($value)) {
                $violations[] = sprintf('%s contains invalid control characters.', $label);
            }
        }

        $postalCode = $this->nullableTrim($data->postalCode);
        if ($postalCode !== null && !preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{1,19}$/', $postalCode)) {
            $violations[] = 'Postal code contains unsupported characters or has an invalid length.';
        }

        $status = strtoupper(trim($data->status));
        if (!in_array($status, self::STATUSES, true)) {
            $violations[] = 'Supplier status must be ACTIVE, INACTIVE or BLOCKED.';
        }

        $country = $this->nullableTrim($data->country);
        if ($country !== null && !preg_match('/^[A-Za-z]{2}$/', $country)) {
            $violations[] = 'Country must be a two-letter ISO 3166-1 alpha-2 code.';
        }

        if ($violations !== []) {
            throw new SupplierValidationException(array_values(array_unique($violations)));
        }
    }

    /** @param list<string> $violations */
    private function maxLength(?string $value, int $max, string $label, array &$violations): void
    {
        $value = $this->nullableTrim($value);
        if ($value !== null && $this->length($value) > $max) {
            $violations[] = sprintf('%s must not exceed %d characters.', $label, $max);
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        return $value === '' ? null : $value;
    }

    private function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }

    private function length(string $value): int
    {
        preg_match_all('/./us', $value, $matches);
        return count($matches[0]);
    }
}
