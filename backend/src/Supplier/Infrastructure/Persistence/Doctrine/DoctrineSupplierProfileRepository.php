<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine;

use App\Supplier\Domain\Model\SupplierProfile;
use App\Supplier\Domain\Model\SupplierBankAccount;
use App\Supplier\Domain\Repository\SupplierProfileRepository;
use App\Supplier\Infrastructure\Audit\SupplierAuditLogger;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DoctrineSupplierProfileRepository implements SupplierProfileRepository
{
    public function __construct(private Connection $connection, private SupplierAuditLogger $audit)
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

            $this->audit->record($profile->supplierId, 'SUPPLIER_PROFILE', $profile->supplierId, $exists ? 'UPDATED' : 'CREATED', ['legalName', 'registrationNumber', 'vatNumber', 'website', 'preferredLanguage', 'preferredCurrency', 'paymentTermsDays']);
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
            $this->lockSupplierForProfileWrite($contact->supplierId);
            if ($contact->primary) {
                $this->connection->executeStatement(
                    "UPDATE supplier_contacts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND contact_type = :contactType AND is_primary = 'Y'",
                    ['supplierId' => $contact->supplierId, 'contactType' => $contact->contactType],
                );
            }
            $this->connection->executeStatement(
                'INSERT INTO supplier_contacts
                    (supplier_id, contact_type, first_name, last_name, job_title, email, phone,
                     is_primary, created_at, updated_at)
                 VALUES (:supplierId, :contactType, :firstName, :lastName, :jobTitle, :email, :phone,
                         :isPrimary, SYSTIMESTAMP, SYSTIMESTAMP)',
                [
                    'supplierId' => $contact->supplierId,
                    'contactType' => $contact->contactType,
                    'firstName' => trim($contact->firstName),
                    'lastName' => trim($contact->lastName),
                    'jobTitle' => $contact->jobTitle,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'isPrimary' => $contact->primary ? 'Y' : 'N',
                ],
            );
            $id = $this->connection->fetchOne(
                'SELECT MAX(id) FROM supplier_contacts WHERE supplier_id = :supplierId',
                ['supplierId' => $contact->supplierId],
            );
            if ($id === false || $id === null) {
                throw new \RuntimeException('Unable to retrieve the created supplier contact.');
            }
            $this->audit->record($contact->supplierId, 'SUPPLIER_CONTACT', (int) $id, 'CREATED', ['contactType', 'firstName', 'lastName', 'jobTitle', 'email', 'phone', 'primary']);
            $this->connection->commit();

            return new \App\Supplier\Domain\Model\SupplierContact(
                (int) $id,
                $contact->supplierId,
                $contact->contactType,
                trim($contact->firstName),
                trim($contact->lastName),
                $contact->jobTitle,
                $contact->email,
                $contact->phone,
                $contact->primary,
            );
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
            $this->lockSupplierForProfileWrite($address->supplierId);
            if ($address->default) {
                $this->connection->executeStatement(
                    "UPDATE supplier_addresses SET is_default = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND address_type = :addressType AND is_default = 'Y'",
                    ['supplierId' => $address->supplierId, 'addressType' => $address->addressType],
                );
            }
            $this->connection->executeStatement(
                'INSERT INTO supplier_addresses
                    (supplier_id, address_type, label, company_name, street, street2, postal_code,
                     city, region, country, is_default, created_at, updated_at)
                 VALUES (:supplierId, :addressType, :label, :companyName, :street, :street2, :postalCode,
                         :city, :region, :country, :isDefault, SYSTIMESTAMP, SYSTIMESTAMP)',
                [
                    'supplierId' => $address->supplierId,
                    'addressType' => $address->addressType,
                    'label' => $address->label,
                    'companyName' => $address->companyName,
                    'street' => trim($address->street),
                    'street2' => $address->street2,
                    'postalCode' => trim($address->postalCode),
                    'city' => trim($address->city),
                    'region' => $address->region,
                    'country' => $address->country,
                    'isDefault' => $address->default ? 'Y' : 'N',
                ],
            );
            $id = $this->connection->fetchOne(
                'SELECT MAX(id) FROM supplier_addresses WHERE supplier_id = :supplierId',
                ['supplierId' => $address->supplierId],
            );
            if ($id === false || $id === null) {
                throw new \RuntimeException('Unable to retrieve the created supplier address.');
            }
            $this->audit->record($address->supplierId, 'SUPPLIER_ADDRESS', (int) $id, 'CREATED', ['addressType', 'label', 'companyName', 'street', 'street2', 'postalCode', 'city', 'region', 'country', 'default']);
            $this->connection->commit();

            return new \App\Supplier\Domain\Model\SupplierAddress(
                (int) $id,
                $address->supplierId,
                $address->addressType,
                $address->label,
                $address->companyName,
                trim($address->street),
                $address->street2,
                trim($address->postalCode),
                trim($address->city),
                $address->region,
                $address->country,
                $address->default,
            );
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }




    public function updateContact(\App\Supplier\Domain\Model\SupplierContact $contact): bool
    {
        if ($contact->id === null) return false;
        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($contact->supplierId);
            $existing = $this->connection->fetchAssociative(
                'SELECT id FROM supplier_contacts WHERE supplier_id = :supplierId AND id = :contactId FOR UPDATE',
                ['supplierId' => $contact->supplierId, 'contactId' => $contact->id],
            );
            if ($existing === false) { $this->connection->commit(); return false; }
            if ($contact->primary) {
                $this->connection->executeStatement(
                    "UPDATE supplier_contacts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND contact_type = :contactType AND id <> :contactId AND is_primary = 'Y'",
                    ['supplierId' => $contact->supplierId, 'contactType' => $contact->contactType, 'contactId' => $contact->id],
                );
            }
            $updated = $this->connection->executeStatement(
                'UPDATE supplier_contacts SET contact_type = :contactType, first_name = :firstName,
                 last_name = :lastName, job_title = :jobTitle, email = :email, phone = :phone,
                 is_primary = :isPrimary, updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :supplierId AND id = :contactId',
                ['contactType' => $contact->contactType, 'firstName' => trim($contact->firstName),
                 'lastName' => trim($contact->lastName), 'jobTitle' => $contact->jobTitle,
                 'email' => $contact->email, 'phone' => $contact->phone,
                 'isPrimary' => $contact->primary ? 'Y' : 'N',
                 'supplierId' => $contact->supplierId, 'contactId' => $contact->id],
            );
            $this->audit->record($contact->supplierId, 'SUPPLIER_CONTACT', $contact->id, 'UPDATED', ['contactType', 'firstName', 'lastName', 'jobTitle', 'email', 'phone', 'primary']);
            $this->connection->commit();
            return $updated > 0;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function deleteContact(int $supplierId, int $contactId): bool
    {
        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($supplierId);
            $contact = $this->connection->fetchAssociative(
                'SELECT contact_type, is_primary FROM supplier_contacts
                 WHERE supplier_id = :supplierId AND id = :contactId FOR UPDATE',
                ['supplierId' => $supplierId, 'contactId' => $contactId],
            );
            if ($contact === false) { $this->connection->commit(); return false; }
            if ($contact['IS_PRIMARY'] === 'Y') {
                // Release the function-based unique constraint before promoting
                // the replacement contact of the same type.
                $this->connection->executeStatement(
                    "UPDATE supplier_contacts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND id = :contactId",
                    ['supplierId' => $supplierId, 'contactId' => $contactId],
                );

                $replacementId = $this->connection->fetchOne(
                    'SELECT id FROM supplier_contacts WHERE supplier_id = :supplierId
                     AND contact_type = :contactType AND id <> :contactId
                     ORDER BY id ASC FETCH FIRST 1 ROWS ONLY',
                    ['supplierId' => $supplierId, 'contactType' => $contact['CONTACT_TYPE'], 'contactId' => $contactId],
                );
                if ($replacementId !== false && $replacementId !== null) {
                    $this->connection->executeStatement(
                        "UPDATE supplier_contacts SET is_primary = 'Y', updated_at = SYSTIMESTAMP
                         WHERE supplier_id = :supplierId AND id = :replacementId",
                        ['supplierId' => $supplierId, 'replacementId' => $replacementId],
                    );
                }
            }
            $deleted = $this->connection->executeStatement(
                'DELETE FROM supplier_contacts WHERE supplier_id = :supplierId AND id = :contactId',
                ['supplierId' => $supplierId, 'contactId' => $contactId],
            );
            if ($deleted > 0) {
                $this->audit->record($supplierId, 'SUPPLIER_CONTACT', $contactId, 'DELETED', ['deleted']);
            }
            $this->connection->commit();
            return $deleted > 0;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) $this->connection->rollBack();
            throw $exception;
        }
    }


    public function updateAddress(\App\Supplier\Domain\Model\SupplierAddress $address): bool
    {
        if ($address->id === null) {
            return false;
        }

        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($address->supplierId);
            $existing = $this->connection->fetchAssociative(
                'SELECT address_type, is_default FROM supplier_addresses
                 WHERE supplier_id = :supplierId AND id = :addressId FOR UPDATE',
                ['supplierId' => $address->supplierId, 'addressId' => $address->id],
            );
            if ($existing === false) {
                $this->connection->commit();
                return false;
            }

            $oldType = (string) $existing['ADDRESS_TYPE'];
            $wasDefault = $existing['IS_DEFAULT'] === 'Y';

            if ($wasDefault) {
                $this->connection->executeStatement(
                    "UPDATE supplier_addresses SET is_default = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND id = :addressId",
                    ['supplierId' => $address->supplierId, 'addressId' => $address->id],
                );
            }

            if ($address->default) {
                $this->connection->executeStatement(
                    "UPDATE supplier_addresses SET is_default = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND address_type = :addressType
                       AND id <> :addressId AND is_default = 'Y'",
                    ['supplierId' => $address->supplierId, 'addressType' => $address->addressType, 'addressId' => $address->id],
                );
            }

            $updated = $this->connection->executeStatement(
                'UPDATE supplier_addresses
                 SET address_type = :addressType, label = :label, company_name = :companyName,
                     street = :street, street2 = :street2, postal_code = :postalCode,
                     city = :city, region = :region, country = :country,
                     is_default = :isDefault, updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :supplierId AND id = :addressId',
                [
                    'addressType' => $address->addressType,
                    'label' => $address->label,
                    'companyName' => $address->companyName,
                    'street' => trim($address->street),
                    'street2' => $address->street2,
                    'postalCode' => trim($address->postalCode),
                    'city' => trim($address->city),
                    'region' => $address->region,
                    'country' => $address->country,
                    'isDefault' => $address->default ? 'Y' : 'N',
                    'supplierId' => $address->supplierId,
                    'addressId' => $address->id,
                ],
            );

            if ($wasDefault && (!$address->default || $oldType !== $address->addressType)) {
                $replacementId = $this->connection->fetchOne(
                    'SELECT id FROM supplier_addresses
                     WHERE supplier_id = :supplierId AND address_type = :addressType
                       AND id <> :addressId
                     ORDER BY id ASC FETCH FIRST 1 ROWS ONLY',
                    ['supplierId' => $address->supplierId, 'addressType' => $oldType, 'addressId' => $address->id],
                );
                if ($replacementId !== false && $replacementId !== null) {
                    $this->connection->executeStatement(
                        "UPDATE supplier_addresses SET is_default = 'Y', updated_at = SYSTIMESTAMP
                         WHERE supplier_id = :supplierId AND id = :replacementId",
                        ['supplierId' => $address->supplierId, 'replacementId' => $replacementId],
                    );
                }
            }

            $this->audit->record($address->supplierId, 'SUPPLIER_ADDRESS', $address->id, 'UPDATED', ['addressType', 'label', 'companyName', 'street', 'street2', 'postalCode', 'city', 'region', 'country', 'default']);
            $this->connection->commit();
            return $updated > 0;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteAddress(int $supplierId, int $addressId): bool
    {
        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($supplierId);
            $address = $this->connection->fetchAssociative(
                'SELECT address_type, is_default FROM supplier_addresses
                 WHERE supplier_id = :supplierId AND id = :addressId FOR UPDATE',
                ['supplierId' => $supplierId, 'addressId' => $addressId],
            );
            if ($address === false) {
                $this->connection->commit();
                return false;
            }

            $wasDefault = $address['IS_DEFAULT'] === 'Y';
            $addressType = (string) $address['ADDRESS_TYPE'];
            if ($wasDefault) {
                $this->connection->executeStatement(
                    "UPDATE supplier_addresses SET is_default = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND id = :addressId",
                    ['supplierId' => $supplierId, 'addressId' => $addressId],
                );
            }

            $deleted = $this->connection->executeStatement(
                'DELETE FROM supplier_addresses WHERE supplier_id = :supplierId AND id = :addressId',
                ['supplierId' => $supplierId, 'addressId' => $addressId],
            );

            if ($wasDefault) {
                $replacementId = $this->connection->fetchOne(
                    'SELECT id FROM supplier_addresses
                     WHERE supplier_id = :supplierId AND address_type = :addressType
                     ORDER BY id ASC FETCH FIRST 1 ROWS ONLY',
                    ['supplierId' => $supplierId, 'addressType' => $addressType],
                );
                if ($replacementId !== false && $replacementId !== null) {
                    $this->connection->executeStatement(
                        "UPDATE supplier_addresses SET is_default = 'Y', updated_at = SYSTIMESTAMP
                         WHERE supplier_id = :supplierId AND id = :replacementId",
                        ['supplierId' => $supplierId, 'replacementId' => $replacementId],
                    );
                }
            }

            if ($deleted > 0) {
                $this->audit->record($supplierId, 'SUPPLIER_ADDRESS', $addressId, 'DELETED', ['deleted']);
            }
            $this->connection->commit();
            return $deleted > 0;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Serialize profile writes per supplier. This prevents concurrent requests
     * from racing while replacing a primary contact, default address or
     * primary bank account.
     */
    private function lockSupplierForProfileWrite(int $supplierId): void
    {
        $lockedId = $this->connection->fetchOne(
            'SELECT id FROM suppliers WHERE id = :supplierId FOR UPDATE',
            ['supplierId' => $supplierId],
        );

        if ($lockedId === false || $lockedId === null) {
            throw new \RuntimeException('Supplier no longer exists while updating its profile.');
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
            $this->lockSupplierForProfileWrite($account->supplierId);
            if ($account->primary) {
                $this->connection->executeStatement(
                    "UPDATE supplier_bank_accounts SET is_primary = 'N', updated_at = SYSTIMESTAMP
                     WHERE supplier_id = :supplierId AND is_primary = 'Y'",
                    ['supplierId' => $account->supplierId],
                );
            }

            $this->connection->executeStatement(
                'INSERT INTO supplier_bank_accounts
                    (supplier_id, account_holder_encrypted, iban_encrypted, bic, bank_name, currency,
                     encryption_key_version, is_primary, created_at, updated_at)
                 VALUES (:supplierId, :holderEncrypted, :ibanEncrypted, :bic, :bankName, :currency,
                         :keyVersion, :isPrimary, SYSTIMESTAMP, SYSTIMESTAMP)',
                [
                    'supplierId' => $account->supplierId,
                    'holderEncrypted' => $account->accountHolderEncrypted,
                    'ibanEncrypted' => $account->ibanEncrypted,
                    'bic' => $account->bic,
                    'bankName' => $account->bankName,
                    'currency' => $account->currency,
                    'keyVersion' => $account->encryptionKeyVersion,
                    'isPrimary' => $account->primary ? 'Y' : 'N',
                ],
            );

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

            $this->audit->record($account->supplierId, 'SUPPLIER_BANK_ACCOUNT', (int) $id, 'CREATED', ['accountHolder', 'iban', 'bic', 'bankName', 'currency', 'primary']);
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
            $this->lockSupplierForProfileWrite($supplierId);
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
            $this->audit->record($supplierId, 'SUPPLIER_BANK_ACCOUNT', $accountId, 'UPDATED', ['primary']);

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
        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($supplierId);
            $exists = $this->connection->fetchOne(
                'SELECT id FROM supplier_bank_accounts WHERE supplier_id = :supplierId AND id = :accountId FOR UPDATE',
                ['supplierId' => $supplierId, 'accountId' => $accountId],
            );
            if ($exists === false) {
                $this->connection->commit();
                return false;
            }

            $deleted = $this->connection->executeStatement(
                'DELETE FROM supplier_bank_accounts WHERE supplier_id = :supplierId AND id = :accountId',
                ['supplierId' => $supplierId, 'accountId' => $accountId],
            );
            if ($deleted > 0) {
                $this->audit->record($supplierId, 'SUPPLIER_BANK_ACCOUNT', $accountId, 'DELETED', ['deleted']);
            }
            $this->connection->commit();
            return $deleted > 0;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }


    public function updateBankAccountEncryption(
        int $supplierId,
        int $accountId,
        string $accountHolderEncrypted,
        string $ibanEncrypted,
        string $keyVersion,
    ): bool {
        $this->connection->beginTransaction();
        try {
            $this->lockSupplierForProfileWrite($supplierId);
            $updated = $this->connection->executeStatement(
                'UPDATE supplier_bank_accounts
                 SET account_holder_encrypted = :holderEncrypted,
                     iban_encrypted = :ibanEncrypted,
                     encryption_key_version = :keyVersion,
                     updated_at = SYSTIMESTAMP
                 WHERE supplier_id = :supplierId AND id = :accountId',
                [
                    'holderEncrypted' => $accountHolderEncrypted,
                    'ibanEncrypted' => $ibanEncrypted,
                    'keyVersion' => $keyVersion,
                    'supplierId' => $supplierId,
                    'accountId' => $accountId,
                ],
            );
            if ($updated > 0) {
                $this->audit->record($supplierId, 'SUPPLIER_BANK_ACCOUNT', $accountId, 'SECURITY_ROTATED', ['encryptionKeyVersion']);
            }
            $this->connection->commit();
            return $updated > 0;
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

}
