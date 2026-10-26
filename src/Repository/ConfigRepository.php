<?php

namespace App\Repository;

use App\Entity\Config;
use Doctrine\ORM\EntityRepository;

class ConfigRepository extends EntityRepository
{
    public function getConfig()
    {
        return $this->createQueryBuilder('q')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns the config row, creating one with default values when the
     * database has none yet.
     */
    public function getOrCreateConfig(): Config
    {
        $config = $this->getConfig();
        if ($config === null)
        {
            $config = new Config();
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }

        return $config;
    }
}
