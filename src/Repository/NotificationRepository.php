<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnread(): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.isRead = :read')
            ->setParameter('read', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasSimilarSince(string $message, int $minutes = 1440): bool
    {
        $since = new \DateTimeImmutable('-' . $minutes . ' minutes');

        return (bool) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.message = :message')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('message', $message)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
