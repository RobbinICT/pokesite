<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class MinifigureRepository extends EntityRepository
{
    /**
     * @param bool $wanted false: the collection, true: the wishlist
     */
    public function getMinifigures(
        ?string $search_term = null,
        ?int $offset = null,
        ?int $limit = null,
        bool $wanted = false
    ) {
        $qb = $this->createQueryBuilder('q')
            ->where('q.wanted = :wanted')
            ->setParameter('wanted', $wanted);

        if ($search_term) {
            $qb->andWhere('LOWER(q.name) LIKE LOWER(:search) OR LOWER(q.identifier) LIKE LOWER(:search) OR LOWER(q.series) LIKE LOWER(:search)')
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

    /** @param bool $wanted false: the collection, true: the wishlist */
    public function countMinifigures(bool $wanted = false): int
    {
        return $this->count(['wanted' => $wanted]);
    }

    /**
     * Number of owned and wanted figures per series, in series order.
     * @return array<string, array{owned: int, wanted: int}>
     */
    public function getSeriesCounts(): array
    {
        $rows = $this->createQueryBuilder('q')
            ->select('q.series AS series, q.wanted AS wanted, COUNT(q.id) AS amount')
            ->groupBy('q.series, q.wanted')
            ->orderBy('q.series', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['series']] ??= ['owned' => 0, 'wanted' => 0];
            $counts[$row['series']][$row['wanted'] ? 'wanted' : 'owned'] = (int) $row['amount'];
        }
        return $counts;
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

    /** @return string[] */
    public function getIdentifiers(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.identifier')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function clearMinifigures()
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->getQuery()
            ->getResult();
    }
}
