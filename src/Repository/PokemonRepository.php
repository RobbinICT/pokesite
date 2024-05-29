<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class PokemonRepository extends EntityRepository
{

    public function getPokemon(?string $search_term = null, bool $only_show_final_list = false)
    {
        $qb = $this->createQueryBuilder('q');

        if ($search_term) {
            $qb->where('LOWER(q.name) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search_term.'%');
        }

        if ($only_show_final_list)
        {
            $qb->andWhere('q.list = :f_list')
                ->setParameter('f_list', 'F');
        }

        return $qb
            ->orderBy('q.dex_nr', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getPokemonSerieNumbersBySerie(string $serie_name): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.serie_nr')
            ->where('q.serie = :serie_name')
            ->setParameter('serie_name', $serie_name)
            ->orderBy('q.dex_nr', 'ASC')
            ->getQuery()
            ->getScalarResult();
    }

    public function getPokemonBySerie(string $serie_name)
    {
        return $this->createQueryBuilder('q')
            ->where('q.serie = :serie_name')
            ->setParameter('serie_name', $serie_name)
            ->orderBy('q.dex_nr', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getBatchOfPokemonWithoutURL(int $batch_size, bool $just_f = false)
    {
        $q = $this->createQueryBuilder('q')
            ->where('q.url IS NULL')
            ->andWhere('q.list != :jumbo')
            ->setParameter('jumbo', 'Jumbo');

        if ($just_f)
        {
            $q->andWhere('q.list = :f_list')
                ->setParameter('f_list', 'F');
        }

        return $q->setMaxResults($batch_size)
            ->getQuery()
            ->getResult();
    }

    public function getImageUrls()
    {
        return $this->createQueryBuilder('q')
            ->select('q.url')
            ->where('q.list = :final')
            ->setParameter('final', 'F')
            ->getQuery()
            ->getResult();
    }

    public function clearPokemon()
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->getQuery()
            ->getResult();
    }
}