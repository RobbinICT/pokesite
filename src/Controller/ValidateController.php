<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class ValidateController
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/validate/url', name: 'validate')]
    public function validateUrl(Request $request)
    {
        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong = [];
        foreach ($all as $pokemon) {
            if ($pokemon->getUrl() !== 'TODO' && $pokemon->getUrl() !== '') {
                if (!str_contains($pokemon->getUrl(), strtok($pokemon->getCleanName(), ' '))) {
                    $wrong[$pokemon->getUrl()] = $pokemon->getCleanName();
                }
            }
        }
        return new JsonResponse(['count' => count($wrong), 'wrong' => $wrong]);
    }

    public function validatePokemon(Request $request)
    {
        // Ndex icm Name
    }

    private function validateGen(Request $request)
    {

    }
}