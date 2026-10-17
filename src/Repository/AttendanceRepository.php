<?php

namespace App\Repository;

use App\Entity\Attendance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    public function findOneByEmployeeAndDate(int $employeeId, \DateTimeInterface $date): ?Attendance
    {
        return $this->findOneBy(['employee' => $employeeId, 'date' => $date]);
    }

    public function summaryForMonth(string $period): array
    {
        $from = new \DateTimeImmutable($period . '-01');
        $to = $from->modify('+1 month');

        $rows = $this->createQueryBuilder('a')
            ->select('e.name AS name', 'a.status', 'COUNT(a.id) AS cnt')
            ->join('a.employee', 'e')
            ->where('a.date >= :from')
            ->andWhere('a.date < :to')
            ->groupBy('e.name', 'a.status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
