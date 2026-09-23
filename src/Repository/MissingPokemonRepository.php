<?php

namespace App\Repository;

use App\Service\PokemonManager;
use Doctrine\ORM\EntityRepository;

class MissingPokemonRepository extends EntityRepository
{
    public function findAllMissingPokemon(
        ?string $search_term,
        bool $exclude_paradox_rift,
        ?int $offset = null,
        ?int $limit = null
    ) {
        $qb = $this->createQueryBuilder('q');
        if ($exclude_paradox_rift === true)
        {
            $qb
                ->andWhere('q.serie != :paradox_rift')
                ->setParameter('paradox_rift', PokemonManager::SERIE_SV_PARADOX_RIFT);
        }

        if ($search_term)
        {
            $qb
                ->andWhere('LOWER(q.title) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search_term.'%');
        }

        // Order by serie so each serie's rows are contiguous — required for the
        // serie section headers to stay correct across infinite-scroll batches.
        $qb->orderBy('q.serie', 'ASC')
            ->addOrderBy('q.serie_nr', 'ASC')
            ->addOrderBy('q.id', 'ASC');

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countMissingPokemon(?string $search_term, bool $exclude_paradox_rift): int
    {
        $qb = $this->createQueryBuilder('q')->select('COUNT(q.id)');

        if ($exclude_paradox_rift === true)
        {
            $qb->andWhere('q.serie != :paradox_rift')
                ->setParameter('paradox_rift', PokemonManager::SERIE_SV_PARADOX_RIFT);
        }

        if ($search_term)
        {
            $qb->andWhere('LOWER(q.title) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search_term.'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getIncompleteSeries(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.serie')
            ->distinct()
            ->getQuery()->getArrayResult();
    }
}