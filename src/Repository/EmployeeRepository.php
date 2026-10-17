<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.active = :active')
            ->setParameter('active', true)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
