<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class MinifigureRepository extends EntityRepository
{
    public function getMinifigures(
        ?string $search_term = null,
        ?int $offset = null,
        ?int $limit = null
    ) {
        $qb = $this->createQueryBuilder('q');

        if ($search_term) {
            $qb->where('LOWER(q.name) LIKE LOWER(:search) OR LOWER(q.identifier) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search_term.'%');
        }

        // Deterministic ordering so offset paging stays stable across
        // infinite-scroll batches.
        $qb->orderBy('q.series', 'ASC')
            ->addOrderBy('q.identifier', 'ASC')
            ->addOrderBy('q.id', 'ASC');

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function getMinifiguresWithoutUrl(?int $limit = null)
    {
        $qb = $this->createQueryBuilder('q')
            ->where('q.url IS NULL OR q.url = :empty_string')
            ->setParameter('empty_string', '');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function clearMinifigures()
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->getQuery()
            ->getResult();
    }
}
