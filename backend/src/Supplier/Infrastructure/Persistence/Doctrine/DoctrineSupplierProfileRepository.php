<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine;

use App\Supplier\Domain\Model\SupplierProfile;
use App\Supplier\Domain\Model\SupplierBankAccount;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DoctrineSupplierProfileRepository implements SupplierProfileRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findBySupplierId(int $supplierId): ?SupplierProfile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT supplier_id, legal_name, registration_number, vat_number, website,
                    preferred_language, preferred_currency, payment_terms_days
             FROM supplier_profiles WHERE supplier_id = :supplierId',
            ['supplierId' => $supplierId],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(SupplierProfile $profile): SupplierProfile
    {
        $this->connection->beginTransaction();

        try {
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM supplier_profiles WHERE supplier_id = :supplierId',
                ['supplierId' => $profile->supplierId],
            ) > 0;

            $values = [
                'legalName' => $profile->legalName,
                'registrationNumber' => $profile->registrationNumber,
                'vatNumber' => $profile->vatNumber,
                'website' => $profile->website,
                'preferredLanguage' => $profile->preferredLanguage,
                'preferredCurrency' => $profile->preferredCurrency,
                'paymentTermsDays' => $profile->paymentTermsDays,
                'supplierId' => $profile->supplierId,
            ];

            if ($exists) {
                $this->connection->executeStatement(
                    'UPDATE supplier_profiles
                     SET legal_name = :legalName, registration_number = :registrationNumber,
                         vat_number = :vatNumber, website = :website,
                         preferred_language = :preferredLanguage, preferred_currency = :preferredCurrency,
                         payment_terms_days = :paymentTermsDays, updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId',
                    $values,
                );
            } else {
                $this->connection->executeStatement(
                    'INSERT INTO supplier_profiles
                     (supplier_id, legal_name, registration_number, vat_number, website,
                      preferred_language, preferred_currency, payment_terms_days, created_at, updated_at)
                     VALUES (:supplierId, :legalName, :registrationNumber, :vatNumber, :website,
                             :preferredLanguage, :preferredCurrency, :paymentTermsDays, SYSTIMESTAMP, SYSTIMESTAMP)',
                    $values,
                );
            }

            $this->connection->commit();
            return $profile;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SupplierProfile
    {
        return new SupplierProfile(
            (int) $row['SUPPLIER_ID'],
            $row['LEGAL_NAME'] !== null ? (string) $row['LEGAL_NAME'] : null,
            $row['REGISTRATION_NUMBER'] !== null ? (string) $row['REGISTRATION_NUMBER'] : null,
            $row['VAT_NUMBER'] !== null ? (string) $row['VAT_NUMBER'] : null,
            $row['WEBSITE'] !== null ? (string) $row['WEBSITE'] : null,
            (string) $row['PREFERRED_LANGUAGE'],
            trim((string) $row['PREFERRED_CURRENCY']),
            (int) $row['PAYMENT_TERMS_DAYS'],
        );
    }
    
    /** @return list<\App\Supplier\Domain\Model\SupplierContact> */
    public function findContacts(int $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, supplier_id, contact_type, first_name, last_name, job_title, email, phone, is_primary
             FROM supplier_contacts WHERE supplier_id = :supplierId ORDER BY is_primary DESC, id ASC',
            ['supplierId' => $supplierId],
        );

        return array_map(static fn (array $row): \App\Supplier\Domain\Model\SupplierContact => new \App\Supplier\Domain\Model\SupplierContact(
            (int) $row['ID'],
            (int) $row['SUPPLIER_ID'],
            (string) $row['CONTACT_TYPE'],
            (string) $row['FIRST_NAME'],
            (string) $row['LAST_NAME'],
            $row['JOB_TITLE'] !== null ? (string) $row['JOB_TITLE'] : null,
            $row['EMAIL'] !== null ? (string) $row['EMAIL'] : null,
            $row['PHONE'] !== null ? (string) $row['PHONE'] : null,
            $row['IS_PRIMARY'] === 'Y',
        ), $rows);
    }

    public function addContact(\App\Supplier\Domain\Model\SupplierContact $contact): \App\Supplier\Domain\Model\SupplierContact
    {
        $this->connection->beginTransaction();
        try {
            if ($contact->primary) {
                $this->connection->executeStatement(
                    "UPDATE supplier_contacts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND contact_type = :contactType AND is_primary = 'Y'",
                    ['supplierId' => $contact->supplierId, 'contactType' => $contact->contactType],
                );
            }
            $this->connection->insert('supplier_contacts', [
                'supplier_id' => $contact->supplierId,
                'contact_type' => $contact->contactType,
                'first_name' => trim($contact->firstName),
                'last_name' => trim($contact->lastName),
                'job_title' => $contact->jobTitle,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->primary ? 'Y' : 'N',
                'created_at' => new \DateTimeImmutable(),
                'updated_at' => new \DateTimeImmutable(),
            ]);
            $this->connection->commit();
            return $contact;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<\App\Supplier\Domain\Model\SupplierAddress> */
    public function findAddresses(int $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, supplier_id, address_type, label, company_name, street, street2, postal_code,
                    city, region, country, is_default
             FROM supplier_addresses WHERE supplier_id = :supplierId ORDER BY is_default DESC, id ASC',
            ['supplierId' => $supplierId],
        );

        return array_map(static fn (array $row): \App\Supplier\Domain\Model\SupplierAddress => new \App\Supplier\Domain\Model\SupplierAddress(
            (int) $row['ID'],
            (int) $row['SUPPLIER_ID'],
            (string) $row['ADDRESS_TYPE'],
            $row['LABEL'] !== null ? (string) $row['LABEL'] : null,
            $row['COMPANY_NAME'] !== null ? (string) $row['COMPANY_NAME'] : null,
            (string) $row['STREET'],
            $row['STREET2'] !== null ? (string) $row['STREET2'] : null,
            (string) $row['POSTAL_CODE'],
            (string) $row['CITY'],
            $row['REGION'] !== null ? (string) $row['REGION'] : null,
            trim((string) $row['COUNTRY']),
            $row['IS_DEFAULT'] === 'Y',
        ), $rows);
    }

    public function addAddress(\App\Supplier\Domain\Model\SupplierAddress $address): \App\Supplier\Domain\Model\SupplierAddress
    {
        $this->connection->beginTransaction();
        try {
            if ($address->default) {
                $this->connection->executeStatement(
                    "UPDATE supplier_addresses SET is_default = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND address_type = :addressType AND is_default = 'Y'",
                    ['supplierId' => $address->supplierId, 'addressType' => $address->addressType],
                );
            }
            $this->connection->insert('supplier_addresses', [
                'supplier_id' => $address->supplierId,
                'address_type' => $address->addressType,
                'label' => $address->label,
                'company_name' => $address->companyName,
                'street' => trim($address->street),
                'street2' => $address->street2,
                'postal_code' => trim($address->postalCode),
                'city' => trim($address->city),
                'region' => $address->region,
                'country' => $address->country,
                'is_default' => $address->default ? 'Y' : 'N',
                'created_at' => new \DateTimeImmutable(),
                'updated_at' => new \DateTimeImmutable(),
            ]);
            $this->connection->commit();
            return $address;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }


    /** @return list<SupplierBankAccount> */
    public function findBankAccounts(int $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, supplier_id, account_holder_encrypted, iban_encrypted, bic, bank_name,
                    currency, encryption_key_version, is_primary
             FROM supplier_bank_accounts
             WHERE supplier_id = :supplierId
             ORDER BY is_primary DESC, id ASC',
            ['supplierId' => $supplierId],
        );

        return array_map(static fn (array $row): SupplierBankAccount => new SupplierBankAccount(
            (int) $row['ID'],
            (int) $row['SUPPLIER_ID'],
            (string) $row['ACCOUNT_HOLDER_ENCRYPTED'],
            (string) $row['IBAN_ENCRYPTED'],
            '',
            $row['BIC'] !== null ? trim((string) $row['BIC']) : null,
            $row['BANK_NAME'] !== null ? (string) $row['BANK_NAME'] : null,
            trim((string) $row['CURRENCY']),
            (string) $row['ENCRYPTION_KEY_VERSION'],
            $row['IS_PRIMARY'] === 'Y',
        ), $rows);
    }

    public function addBankAccount(SupplierBankAccount $account): SupplierBankAccount
    {
        $this->connection->beginTransaction();

        try {
            if ($account->primary) {
                $this->connection->executeStatement(
                    "UPDATE supplier_bank_accounts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND is_primary = 'Y'",
                    ['supplierId' => $account->supplierId],
                );
            }

            $this->connection->insert('supplier_bank_accounts', [
                'supplier_id' => $account->supplierId,
                'account_holder_encrypted' => $account->accountHolderEncrypted,
                'iban_encrypted' => $account->ibanEncrypted,
                'bic' => $account->bic,
                'bank_name' => $account->bankName,
                'currency' => $account->currency,
                'encryption_key_version' => $account->encryptionKeyVersion,
                'is_primary' => $account->primary ? 'Y' : 'N',
                'created_at' => new \DateTimeImmutable(),
                'updated_at' => new \DateTimeImmutable(),
            ]);

            $id = $this->connection->fetchOne(
                'SELECT id FROM supplier_bank_accounts
                 WHERE supplier_id = :supplierId AND iban_encrypted = :ibanEncrypted
                   AND account_holder_encrypted = :holderEncrypted',
                [
                    'supplierId' => $account->supplierId,
                    'ibanEncrypted' => $account->ibanEncrypted,
                    'holderEncrypted' => $account->accountHolderEncrypted,
                ],
            );

            if ($id === false) {
                throw new \RuntimeException('Unable to retrieve the created supplier bank account.');
            }

            $this->connection->commit();

            return new SupplierBankAccount(
                (int) $id,
                $account->supplierId,
                $account->accountHolderEncrypted,
                $account->ibanEncrypted,
                $account->maskedIban,
                $account->bic,
                $account->bankName,
                $account->currency,
                $account->encryptionKeyVersion,
                $account->primary,
            );
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }


    public function setPrimaryBankAccount(int $supplierId, int $accountId): bool
    {
        $this->connection->beginTransaction();

        try {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM supplier_bank_accounts
                 WHERE supplier_id = :supplierId AND id = :accountId',
                ['supplierId' => $supplierId, 'accountId' => $accountId],
            );

            if ($exists === false) {
                $this->connection->commit();
                return false;
            }

            $this->connection->executeStatement(
                "UPDATE supplier_bank_accounts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :supplierId AND is_primary = 'Y'",
                ['supplierId' => $supplierId],
            );
            $this->connection->executeStatement(
                "UPDATE supplier_bank_accounts SET is_primary = 'Y', updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :supplierId AND id = :accountId",
                ['supplierId' => $supplierId, 'accountId' => $accountId],
            );

            $this->connection->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteBankAccount(int $supplierId, int $accountId): bool
    {
        $deleted = $this->connection->executeStatement(
            'DELETE FROM supplier_bank_accounts
             WHERE supplier_id = :supplierId AND id = :accountId',
            ['supplierId' => $supplierId, 'accountId' => $accountId],
        );

        return $deleted > 0;
    }

}
