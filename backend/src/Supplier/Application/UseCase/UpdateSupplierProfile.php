<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\DTO\SupplierProfileData;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierProfile;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Domain\Repository\SupplierRepository;

final readonly class UpdateSupplierProfile
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierProfileRepository $profiles,
    ) {
    }

    public function execute(int $supplierId, SupplierProfileData $data): SupplierProfile
    {
        if ($this->suppliers->findById($supplierId) === null) {
            throw SupplierNotFound::withId($supplierId);
        }

        return $this->profiles->save(new SupplierProfile(
            $supplierId,
            self::nullable($data->legalName),
            self::nullable($data->registrationNumber),
            self::nullable($data->vatNumber),
            self::nullable($data->website),
            strtolower(trim($data->preferredLanguage)),
            strtoupper(trim($data->preferredCurrency)),
            $data->paymentTermsDays,
        ));
    }

    private static function nullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        return $value === '' ? null : $value;
    }
}
