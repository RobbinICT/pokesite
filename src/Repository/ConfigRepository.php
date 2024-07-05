<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class ConfigRepository extends EntityRepository
{
    public function getConfig()
    {
        return $this->createQueryBuilder('q')
            ->getQuery()
            ->getOneOrNullResult();
    }
}