<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findNextOrderNo(): string
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.orderNo)')
            ->getQuery()
            ->getSingleScalarResult();

        $max = $result ? (int) substr($result, -5) : 0;

        return 'ORD-' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{total: string, count: int}
     */
    public function salesSummary(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.total), 0) AS total', 'COUNT(o.id) AS cnt')
            ->where('o.status = :status')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('status', Order::STATUS_PAID)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return ['total' => (string) $row['total'], 'count' => (int) $row['cnt']];
    }

    /**
     * @return array<int, array{date: string, total: string}>
     */
    public function salesByDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.createdAt', 'o.total')
            ->where('o.status = :status')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->orderBy('o.createdAt', 'ASC')
            ->setParameter('status', Order::STATUS_PAID)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($rows as $row) {
            $day = $row['createdAt']->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0.0) + (float) $row['total'];
        }

        $result = [];
        foreach ($byDay as $day => $total) {
            $result[] = ['date' => $day, 'total' => (string) round($total, 2)];
        }

        return $result;
    }

    /**
     * @return array<int, array{category: string, total: string}>
     */
    public function salesByCategory(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('o')
            ->select('pc.name AS category', 'COALESCE(SUM(oi.lineTotal), 0) AS total')
            ->join('o.items', 'oi')
            ->join('oi.product', 'p')
            ->join('p.category', 'pc')
            ->where('o.status = :status')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->groupBy('pc.name')
            ->setParameter('status', Order::STATUS_PAID)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
