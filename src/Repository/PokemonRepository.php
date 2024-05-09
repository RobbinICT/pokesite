<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class PokemonRepository extends EntityRepository
{

    public function getPokemon(?string $search_term)
    {
        $qb = $this->createQueryBuilder('q');
        if ($search_term) {
            $qb->where('LOWER(q.name) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search_term.'%');
        }

        if (true)
        {
            $qb->andWhere('q.list = :f_list')
                ->setParameter('f_list', 'F');
        }

        return $qb
            ->orderBy('q.dex_nr', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getPokemonByGen(int $gen, bool $is_final = false)
    {
        $qb = $this->createQueryBuilder('q')
            ->where('q.gen = :gen')
            ->setParameter('gen', $gen);

        if ($is_final)
        {
            $qb->andWhere('q.list = :f_list')
                ->setParameter('f_list', 'F');
        }

        return $qb
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

    public function getFinalList()
    {
        return $this->createQueryBuilder('q')
            ->where('q.list = :list')
            ->setParameter('list', 'F')
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