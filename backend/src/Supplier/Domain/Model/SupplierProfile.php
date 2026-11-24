<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Model;

use InvalidArgumentException;

final readonly class SupplierProfile
{
    public function __construct(
        public int $supplierId,
        public ?string $legalName,
        public ?string $registrationNumber,
        public ?string $vatNumber,
        public ?string $website,
        public string $preferredLanguage = 'de',
        public string $preferredCurrency = 'EUR',
        public int $paymentTermsDays = 30,
    ) {
        if ($supplierId < 1) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        if ($paymentTermsDays < 0 || $paymentTermsDays > 365) {
            throw new InvalidArgumentException('Payment terms must be between 0 and 365 days.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $preferredCurrency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }
        if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $preferredLanguage)) {
            throw new InvalidArgumentException('Preferred language must be a valid language tag.');
        }

        if ($website !== null) {
            $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
            if (
                filter_var($website, FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)
                || parse_url($website, PHP_URL_HOST) === null
            ) {
                throw new InvalidArgumentException('Website must be a valid HTTP or HTTPS URL.');
            }
        }

        foreach ([
            'Legal name' => [$legalName, 200],
            'Registration number' => [$registrationNumber, 80],
            'VAT number' => [$vatNumber, 50],
            'Website' => [$website, 255],
        ] as $label => [$value, $max]) {
            if ($value !== null && (trim($value) === '' || mb_strlen($value) > $max)) {
                throw new InvalidArgumentException(sprintf('%s must not be empty and must not exceed %d characters.', $label, $max));
            }
            if ($value !== null && preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new InvalidArgumentException(sprintf('%s contains invalid control characters.', $label));
            }
        }
    }
}
