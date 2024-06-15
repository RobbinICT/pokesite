<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\ValidateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ValidateController extends AbstractController
{
    private ValidateManager $validate_manager;
    private EntityManagerInterface $entity_manager;

    public function __construct(ValidateManager $validate_manager, EntityManagerInterface $entity_manager)
    {
        $this->validate_manager = $validate_manager;
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/validate/all', name: 'validate_all')]
    public function validateAll(Request $request)
    {
        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong_url = $this->validate_manager->validateUrl($all);
        $wrong_gen = $this->validate_manager->validateGeneration($all);

        $url_response = \count($wrong_url) > 0 ? ['count' => \count($wrong_url), 'wrong_url' => $wrong_url] : ['success'];
        $gen_response = \count($wrong_gen) > 0 ? ['count' => \count($wrong_gen), 'wrong_gen' => $wrong_gen] : ['success'];
        return new JsonResponse([
            'urls' => $url_response,
            'gens' => $gen_response,
        ]);
    }

    #[Route(path: '/validate/url', name: 'validate_url')]
    public function validateUrl(Request $request)
    {
        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong = $this->validate_manager->validateUrl($all);
        return new JsonResponse(['count' => count($wrong), 'wrong' => $wrong]);
    }

    public function validatePokemon(Request $request)
    {
        // Ndex icm Name
    }

    #[Route(path: '/validate/gen', name: 'validate_generation')]
    public function validateGen(Request $request)
    {
        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong = $this->validate_manager->validateGeneration($all);
        return new JsonResponse(['count' => count($wrong), 'wrong' => $wrong]);
    }
}