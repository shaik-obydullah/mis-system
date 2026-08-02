<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLowStock(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.currentStock <= p.lowStockThreshold')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.currentStock', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
