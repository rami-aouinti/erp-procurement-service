<?php

declare(strict_types=1);

namespace App\Supplier\Domain\Model;

use InvalidArgumentException;

final readonly class SupplierBankAccount
{
    public function __construct(
        public ?int $id,
        public int $supplierId,
        public string $accountHolderEncrypted,
        public string $ibanEncrypted,
        public string $maskedIban,
        public ?string $bic,
        public ?string $bankName,
        public string $currency,
        public string $encryptionKeyVersion,
        public bool $primary = false,
    ) {
        if ($supplierId < 1 || $accountHolderEncrypted === '' || $ibanEncrypted === '') {
            throw new InvalidArgumentException('Supplier and encrypted bank account data are required.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Bank account currency must be a three-letter ISO code.');
        }
        if ($bic !== null && !preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
            throw new InvalidArgumentException('BIC must be a valid 8- or 11-character code.');
        }
        if (mb_strlen($bankName ?? '') > 150 || mb_strlen($encryptionKeyVersion) > 30) {
            throw new InvalidArgumentException('Bank name or encryption key version is too long.');
        }
    }
}
