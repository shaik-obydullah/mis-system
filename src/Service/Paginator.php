<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;

class Paginator
{
    /**
     * @return array{items: array, total: int, page: int, limit: int, pages: int}
     */
    public function paginate(QueryBuilder $qb, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $alias = $qb->getRootAliases()[0];

        $total = (int) (clone $qb)
            ->select('COUNT(' . $alias . '.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = (clone $qb)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }
}
