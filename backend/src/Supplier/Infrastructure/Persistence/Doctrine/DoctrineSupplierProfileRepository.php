<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine;

use App\Supplier\Domain\Model\SupplierProfile;
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
}
