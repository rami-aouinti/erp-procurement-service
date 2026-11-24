<?php

declare(strict_types=1);

namespace App\Supplier\Domain\ValueObject;

use App\Supplier\Domain\Exception\InvalidSupplierData;

final readonly class SupplierAddress
{
    public function __construct(
        public ?string $street,
        public ?string $city,
        public ?string $postalCode,
        public ?string $country,
    ) {
        self::assertLength($street, 180, 'Street');
        self::assertLength($city, 100, 'City');
        self::assertLength($postalCode, 20, 'Postal code');
        self::assertLength($country, 2, 'Country');
        if ($country !== null && !preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidSupplierData('Country must be a two-letter ISO 3166-1 alpha-2 code.');
        }
    }

    public static function fromNullable(?string $street, ?string $city, ?string $postalCode, ?string $country): self
    {
        $normalize = static function (?string $value): ?string {
            $value = $value !== null ? trim($value) : null;
            return $value === '' ? null : $value;
        };
        $country = $normalize($country);
        return new self($normalize($street), $normalize($city), $normalize($postalCode), $country !== null ? strtoupper($country) : null);
    }

    private static function assertLength(?string $value, int $max, string $label): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            throw new InvalidSupplierData(sprintf('%s must not exceed %d characters.', $label, $max));
        }
    }
}
