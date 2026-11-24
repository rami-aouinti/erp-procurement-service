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
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($preferredCurrency))) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }
        if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $preferredLanguage)) {
            throw new InvalidArgumentException('Preferred language must be a valid language tag.');
        }
        if ($website !== null && filter_var($website, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Website must be a valid URL.');
        }
        foreach ([$legalName => 200, $registrationNumber => 80, $vatNumber => 50, $website => 255] as $value => $max) {
            if ($value !== null && mb_strlen($value) > $max) {
                throw new InvalidArgumentException(sprintf('Profile fields must not exceed %d characters.', $max));
            }
        }
    }
}
