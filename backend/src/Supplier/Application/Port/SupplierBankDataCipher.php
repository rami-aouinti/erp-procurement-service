<?php

declare(strict_types=1);

namespace App\Supplier\Application\Port;

interface SupplierBankDataCipher
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $payload, ?string $keyVersion = null): string;

    public function keyVersion(): string;

    public function maskIban(string $iban): string;
}
