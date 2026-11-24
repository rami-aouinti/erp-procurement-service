<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Persistence\Doctrine;

use App\Supplier\Domain\Model\Supplier;
use App\Supplier\Domain\Repository\SupplierRepository as SupplierRepositoryInterface;
use App\Supplier\Infrastructure\Persistence\Doctrine\Entity\SupplierRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineSupplierRepository extends ServiceEntityRepository implements SupplierRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, Connection $connection)
    {
        parent::__construct($registry, SupplierRecord::class);

        // DBAL's OCI8 driver exchanges date/time values using ISO strings.
        // Oracle's default session NLS formats are locale-dependent (for example
        // "24-NOV-26 05.40.22.584414 PM"), which Doctrine cannot parse reliably.
        // Set deterministic formats for this database session before any query.
        $connection->executeStatement("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
        $connection->executeStatement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
    }

    public function findAll(): array
    {
        $records = $this->createQueryBuilder('supplier')
            ->orderBy('supplier.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map($this->toDomain(...), $records);
    }

    public function findById(int $id): ?Supplier
    {
        $record = $this->find($id);

        return $record instanceof SupplierRecord ? $this->toDomain($record) : null;
    }

    public function save(Supplier $supplier): Supplier
    {
        $em = $this->getEntityManager();

        if ($supplier->id() === null) {
            $record = new SupplierRecord(
                $supplier->name(),
                $supplier->email(),
                $supplier->phone(),
                $supplier->taxNumber(),
                $supplier->createdAt(),
            );

            $em->persist($record);
            $em->flush();

            return Supplier::reconstitute(
                (int) $record->id(),
                $record->name(),
                $record->email(),
                $record->phone(),
                $record->taxNumber(),
                $record->createdAt(),
            );
        }

        $record = $this->find($supplier->id());
        if (!$record instanceof SupplierRecord) {
            throw new \RuntimeException('Supplier persistence record disappeared during update.');
        }

        $record->updateFrom($supplier);
        $em->flush();

        return $supplier;
    }

    public function delete(Supplier $supplier): void
    {
        $record = $this->find($supplier->id());

        if ($record instanceof SupplierRecord) {
            $this->getEntityManager()->remove($record);
            $this->getEntityManager()->flush();
        }
    }

    public function emailExists(string $email, ?int $excludingId = null): bool
    {
        $query = $this->createQueryBuilder('supplier')
            ->select('COUNT(supplier.id)')
            ->andWhere('LOWER(supplier.email) = LOWER(:email)')
            ->setParameter('email', trim($email));

        if ($excludingId !== null) {
            $query->andWhere('supplier.id != :excludingId')
                ->setParameter('excludingId', $excludingId);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
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
        );
    }
}
