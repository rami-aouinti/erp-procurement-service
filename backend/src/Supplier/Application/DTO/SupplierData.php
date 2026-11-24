<?php
declare(strict_types=1);
namespace App\Supplier\Application\DTO;
final readonly class SupplierData
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $taxNumber = null,
    ) {}
}
