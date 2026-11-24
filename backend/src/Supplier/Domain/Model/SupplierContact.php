<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Model;

use InvalidArgumentException;

final readonly class SupplierContact
{
    public function __construct(
        public ?int $id,
        public int $supplierId,
        public string $contactType,
        public string $firstName,
        public string $lastName,
        public ?string $jobTitle,
        public ?string $email,
        public ?string $phone,
        public bool $primary = false,
    ) {
        if ($supplierId < 1 || trim($contactType) === '' || mb_strlen($contactType) > 30) {
            throw new InvalidArgumentException('Supplier and contact type are required.');
        }
        foreach (['First name' => [$firstName, 100], 'Last name' => [$lastName, 100]] as $label => [$value, $max]) {
            if (trim($value) === '' || mb_strlen($value) > $max) {
                throw new InvalidArgumentException(sprintf('%s is required and must not exceed %d characters.', $label, $max));
            }
        }
        if ($jobTitle !== null && mb_strlen($jobTitle) > 120) {
            throw new InvalidArgumentException('Job title must not exceed 120 characters.');
        }
        if ($email !== null && (mb_strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('Contact email is invalid.');
        }
        if ($phone !== null && mb_strlen($phone) > 30) {
            throw new InvalidArgumentException('Contact phone must not exceed 30 characters.');
        }
    }
}
