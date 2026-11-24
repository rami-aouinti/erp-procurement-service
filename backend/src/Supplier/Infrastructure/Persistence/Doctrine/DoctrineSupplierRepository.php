<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine;

use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository as SupplierRepositoryInterface;
use App\Supplier\Infrastructure\Persistence\Doctrine\Entity\SupplierRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

final class DoctrineSupplierRepository extends ServiceEntityRepository implements SupplierRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, Connection $connection)
    {
        parent::__construct($registry, SupplierRecord::class);
        $connection->executeStatement("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
        $connection->executeStatement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
    }

    /** @return list<Supplier> */
    public function findAll(): array
    {
        $records = $this->createQueryBuilder('supplier')
            ->orderBy('supplier.name', \SortDirection::Ascending)
            ->getQuery()->getResult();

        return array_map($this->toDomain(...), $records);
    }

    public function findById(int $id): ?Supplier
    {
        $record = $this->find($id);
        return $record instanceof SupplierRecord ? $this->toDomain($record) : null;
    }

    public function save(Supplier $supplier): Supplier
    {
        $connection = $this->getEntityManager()->getConnection();
        $isNew = $supplier->id() === null;
        $connection->beginTransaction();

        try {
            $em = $this->getEntityManager();
            if ($isNew) {
                $record = new SupplierRecord($supplier);
                $em->persist($record);
            } else {
                $record = $this->find($supplier->id());
                if (!$record instanceof SupplierRecord) {
                    throw new \RuntimeException('Supplier persistence record disappeared during update.');
                }
                $record->updateFrom($supplier);
            }

            $em->flush();
            $saved = $this->toDomain($record);
            $this->appendOutboxEvent($isNew ? 'SupplierCreated' : 'SupplierUpdated', $saved);
            $connection->commit();

            return $saved;
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(Supplier $supplier): void
    {
        $record = $this->find($supplier->id());
        if (!$record instanceof SupplierRecord) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();
        try {
            $this->getEntityManager()->remove($record);
            $this->getEntityManager()->flush();
            $connection->executeStatement(
                "INSERT INTO supplier_outbox_events (event_id, event_type, aggregate_id, payload, status, attempts, occurred_at, next_attempt_at)
                 VALUES (RAWTOHEX(SYS_GUID()), 'SupplierDeleted', :supplierId, :payload, 'PENDING', 0, SYSTIMESTAMP, SYSTIMESTAMP)",
                [
                    'supplierId' => $supplier->id(),
                    'payload' => json_encode(['id' => $supplier->id()], JSON_THROW_ON_ERROR),
                ]
            );
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public function emailExists(string $email, ?int $excludingId = null): bool
    {
        $query = $this->createQueryBuilder('supplier')
            ->select('COUNT(supplier.id)')
            ->andWhere('LOWER(supplier.email) = LOWER(:email)')
            ->setParameter('email', trim($email));
        if ($excludingId !== null) {
            $query->andWhere('supplier.id != :excludingId')->setParameter('excludingId', $excludingId);
        }
        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }

    private function appendOutboxEvent(string $eventType, Supplier $supplier): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            "INSERT INTO supplier_outbox_events (event_id, event_type, aggregate_id, payload, status, attempts, occurred_at, next_attempt_at)
             VALUES (RAWTOHEX(SYS_GUID()), :eventType, :supplierId, :payload, 'PENDING', 0, SYSTIMESTAMP, SYSTIMESTAMP)",
            [
                'eventType' => $eventType,
                'supplierId' => $supplier->id(),
                'payload' => json_encode([
                    'id' => $supplier->id(),
                    'name' => $supplier->name(),
                    'email' => $supplier->email(),
                    'phone' => $supplier->phone(),
                    'taxNumber' => $supplier->taxNumber(),
                    'status' => $supplier->status(),
                    'contactPerson' => $supplier->contactPerson(),
                    'street' => $supplier->street(),
                    'city' => $supplier->city(),
                    'postalCode' => $supplier->postalCode(),
                    'country' => $supplier->country(),
                    'createdAt' => $supplier->createdAt()->format(DATE_ATOM),
                ], JSON_THROW_ON_ERROR),
            ]
        );
    }

    private function toDomain(SupplierRecord $record): Supplier
    {
        return Supplier::reconstitute(
            (int) $record->id(),
            $record->name(),
            $record->email(),
            $record->phone(),
            $record->taxNumber(),
            $record->createdAt(),
            $record->status(),
            $record->contactPerson(),
            $record->street(),
            $record->city(),
            $record->postalCode(),
            $record->country(),
        );
    }
}
