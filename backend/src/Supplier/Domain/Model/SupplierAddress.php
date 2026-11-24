<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Model;

use InvalidArgumentException;

final readonly class SupplierAddress
{
    public function __construct(
        public ?int $id,
        public int $supplierId,
        public string $addressType,
        public ?string $label,
        public ?string $companyName,
        public string $street,
        public ?string $street2,
        public string $postalCode,
        public string $city,
        public ?string $region,
        public string $country,
        public bool $default = false,
    ) {
        if ($supplierId < 1 || !in_array($addressType, ['REGISTERED', 'BILLING', 'SHIPPING', 'OTHER'], true)) {
            throw new InvalidArgumentException('Supplier ID or address type is invalid.');
        }
        foreach (['Street' => [$street, 180], 'Postal code' => [$postalCode, 20], 'City' => [$city, 100]] as $label => [$value, $max]) {
            if (trim($value) === '' || mb_strlen($value) > $max) {
                throw new InvalidArgumentException(sprintf('%s is required and must not exceed %d characters.', $label, $max));
            }
        }
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('Country must be an ISO 3166-1 alpha-2 code.');
        }
    }
}
