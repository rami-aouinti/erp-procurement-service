<?php

declare(strict_types=1);

namespace App\Supplier\Application\DTO;

final readonly class SupplierProfileData
{
    public function __construct(
        public ?string $legalName,
        public ?string $registrationNumber,
        public ?string $vatNumber,
        public ?string $website,
        public string $preferredLanguage = 'de',
        public string $preferredCurrency = 'EUR',
        public int $paymentTermsDays = 30,
    ) {
    }
}
