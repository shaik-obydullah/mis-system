<?php

namespace App\Repository;

use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * @return array<int, array{name: string, qty: string, revenue: string}>
     */
    public function topProducts(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array
    {
        return $this->createQueryBuilder('oi')
            ->select('p.name AS name', 'COALESCE(SUM(oi.quantity), 0) AS qty', 'COALESCE(SUM(oi.lineTotal), 0) AS revenue')
            ->join('oi.order', 'o')
            ->join('oi.product', 'p')
            ->where('o.status = :status')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->groupBy('p.name')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('status', 'paid')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
