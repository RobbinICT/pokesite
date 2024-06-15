<?php

namespace App\Repository;

use App\Service\PokemonManager;
use Doctrine\ORM\EntityRepository;

class MissingPokemonRepository  extends EntityRepository
{
    public function findAllMissingPokemon(?string $search_term, bool $exclude_paradox_rift)
    {
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

        return $qb->getQuery()->getResult();
    }
}