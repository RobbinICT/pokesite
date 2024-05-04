<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class PokemonRepository extends EntityRepository
{

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