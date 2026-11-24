<?php

namespace App\Repository;

use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    /** @return list<Supplier> */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('supplier')
            ->orderBy('supplier.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Supplier $supplier, bool $flush = true): void
    {
        $this->getEntityManager()->persist($supplier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Supplier $supplier): void
    {
        $this->getEntityManager()->remove($supplier);
        $this->getEntityManager()->flush();
    }
}
