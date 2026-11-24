<?php

declare(strict_types=1);

namespace App\Supplier\Application\UseCase;

use App\Supplier\Application\Port\SupplierBankDataCipher;
use App\Supplier\Domain\Exception\SupplierNotFound;
use App\Supplier\Domain\Model\SupplierBankAccount;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Domain\Repository\SupplierRepository;
use App\Supplier\Domain\ValueObject\Iban;
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
            $iban = $this->cipher->decrypt($account->ibanEncrypted, $account->encryptionKeyVersion);

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
        $iban = (new Iban($iban))->value;
        $bic = $bic !== null ? strtoupper(trim($bic)) : null;
        $currency = strtoupper(trim($currency));

        if ($accountHolder === '' || mb_strlen($accountHolder) > 200) {
            throw new InvalidArgumentException('Account holder is required and must not exceed 200 characters.');
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


    public function setPrimary(int $supplierId, int $accountId): bool
    {
        $this->assertSupplierExists($supplierId);

        if ($accountId < 1) {
            throw new InvalidArgumentException('Bank account ID must be positive.');
        }

        return $this->profiles->setPrimaryBankAccount($supplierId, $accountId);
    }

    public function delete(int $supplierId, int $accountId): bool
    {
        $this->assertSupplierExists($supplierId);

        if ($accountId < 1) {
            throw new InvalidArgumentException('Bank account ID must be positive.');
        }

        return $this->profiles->deleteBankAccount($supplierId, $accountId);
    }


    /**
     * Re-encrypts every bank account for one supplier with the currently active key.
     * Plaintext exists only in memory during this operation.
     */
    public function rotateEncryptionKey(int $supplierId): int
    {
        $this->assertSupplierExists($supplierId);
        $rotated = 0;
        $currentVersion = $this->cipher->keyVersion();

        foreach ($this->profiles->findBankAccounts($supplierId) as $account) {
            if ($account->encryptionKeyVersion === $currentVersion) {
                continue;
            }

            $accountHolder = $this->cipher->decrypt(
                $account->accountHolderEncrypted,
                $account->encryptionKeyVersion,
            );
            $iban = $this->cipher->decrypt(
                $account->ibanEncrypted,
                $account->encryptionKeyVersion,
            );

            if ($this->profiles->updateBankAccountEncryption(
                $supplierId,
                (int) $account->id,
                $this->cipher->encrypt($accountHolder),
                $this->cipher->encrypt($iban),
                $currentVersion,
            )) {
                ++$rotated;
            }
        }

        return $rotated;
    }

    private function assertSupplierExists(int $supplierId): void
    {
        if ($this->suppliers->findById($supplierId) === null) {
            throw SupplierNotFound::withId($supplierId);
        }
    }

}
