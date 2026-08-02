<?php

namespace App\Repository;

use App\Entity\Expense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    public function totalForRange(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $row = $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.amount), 0) AS total')
            ->where('e.date >= :from')
            ->andWhere('e.date < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return (string) $row['total'];
    }

    /**
     * @return array<int, array{category: string, total: string}>
     */
    public function totalsByCategory(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('e')
            ->select('ec.name AS category', 'COALESCE(SUM(e.amount), 0) AS total')
            ->join('e.category', 'ec')
            ->where('e.date >= :from')
            ->andWhere('e.date < :to')
            ->groupBy('ec.name')
            ->orderBy('total', 'DESC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }
}
