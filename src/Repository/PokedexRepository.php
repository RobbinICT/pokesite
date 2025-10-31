<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class PokedexRepository extends EntityRepository
{
    public function clearPokedex()
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->getQuery()
            ->getResult();
    }
}