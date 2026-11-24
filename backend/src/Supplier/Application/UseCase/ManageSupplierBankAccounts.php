<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\Port\SupplierBankDataCipher;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierBankAccount;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Domain\Repository\SupplierRepository;
use InvalidArgumentException;

final readonly class ManageSupplierBankAccounts
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierProfileRepository $profiles,
        private SupplierBankDataCipher $cipher,
    ) {
    }

    /** @return list<SupplierBankAccount> */
    public function list(int $supplierId): array
    {
        $this->assertSupplierExists($supplierId);

        return array_map(function (SupplierBankAccount $account): SupplierBankAccount {
            $iban = $this->cipher->decrypt($account->ibanEncrypted);

            return new SupplierBankAccount(
                $account->id,
                $account->supplierId,
                $account->accountHolderEncrypted,
                $account->ibanEncrypted,
                $this->cipher->maskIban($iban),
                $account->bic,
                $account->bankName,
                $account->currency,
                $account->encryptionKeyVersion,
                $account->primary,
            );
        }, $this->profiles->findBankAccounts($supplierId));
    }

    public function add(
        int $supplierId,
        string $accountHolder,
        string $iban,
        ?string $bic,
        ?string $bankName,
        string $currency,
        bool $primary,
    ): SupplierBankAccount {
        $this->assertSupplierExists($supplierId);

        $accountHolder = trim($accountHolder);
        $iban = strtoupper((string) preg_replace('/\s+/', '', $iban));
        $bic = $bic !== null ? strtoupper(trim($bic)) : null;
        $currency = strtoupper(trim($currency));

        if ($accountHolder === '' || mb_strlen($accountHolder) > 200) {
            throw new InvalidArgumentException('Account holder is required and must not exceed 200 characters.');
        }
        if (!self::isValidIban($iban)) {
            throw new InvalidArgumentException('IBAN is invalid. Please check its format and checksum.');
        }
        if ($bic !== null && !preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
            throw new InvalidArgumentException('BIC must be a valid 8- or 11-character code.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }
        if ($bankName !== null && mb_strlen($bankName) > 150) {
            throw new InvalidArgumentException('Bank name must not exceed 150 characters.');
        }

        $account = new SupplierBankAccount(
            null,
            $supplierId,
            $this->cipher->encrypt($accountHolder),
            $this->cipher->encrypt($iban),
            $this->cipher->maskIban($iban),
            $bic,
            $bankName,
            $currency,
            $this->cipher->keyVersion(),
            $primary,
        );

        return $this->profiles->addBankAccount($account);
    }

    private function assertSupplierExists(int $supplierId): void
    {
        if ($this->suppliers->findById($supplierId) === null) {
            throw SupplierNotFound::withId($supplierId);
        }
    }

    private static function isValidIban(string $iban): bool
    {
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
