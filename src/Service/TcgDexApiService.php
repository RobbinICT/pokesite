<?php

namespace App\Service;

use App\DTO\TCGDex\SetDTO;
use App\Entity\TCGDex\Set;
use App\Repository\TCGDex\SetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use TCGdex\TCGdex;

class TcgDexApiService
{
    private TCGdex $tcg_dex;

    public function __construct(
        private EntityManagerInterface $entity_manager,
        private SerializerInterface $serializer,
    )
    {
        $this->tcg_dex = new TCGdex('en');
    }

    public function getAllSets()
    {
        $content = $this->tcg_dex->set->list();
        $normalized = $this->serializer->normalize($content);
        $sets_dto = $this->serializer->denormalize(
            $normalized,
            SetDTO::class . '[]',
        );

        $existing_sets = $this->entity_manager->getRepository(Set::class)
            ->createQueryBuilder('s')
            ->indexBy('s', 's.identifier')
            ->getQuery()
            ->getResult();

        /** @var SetDTO $set_dto */
        foreach ($sets_dto as $set_dto)
        {
            /** @var Set $existing_set */
            $existing_set = $existing_sets[$set_dto->id] ?? null;
            if (!$existing_set)
            {
                $set = Set::constructFromDTO($set_dto);
                $this->entity_manager->persist($set);
            }
            else
            {
                $existing_set->updateFromDTO($set_dto);
            }
        }

        $this->entity_manager->flush();
    }
}