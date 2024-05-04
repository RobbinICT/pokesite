<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PokemonController extends AbstractController
{
    private EntityManagerInterface $entity_manager;
    private PokemonManager $pokemon_manager;

    public function __construct(EntityManagerInterface $entity_manager, PokemonManager $pokemon_manager)
    {
        $this->entity_manager = $entity_manager;
        $this->pokemon_manager = $pokemon_manager;
    }

    #[Route(path: '/final', name: 'final')]
    public function getFinalList(Request $request): JsonResponse
    {
        $final_list = $this->entity_manager->getRepository(Pokemon::class)->getFinalList();
        return new JsonResponse($final_list);
    }

    #[Route(path: '/import', name: 'import')]
    public function importCvsIntoDatabase(Request $request)
    {
        try {
            $this->pokemon_manager->import();
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'Imported successfully'
        ], Response::HTTP_OK);
    }

    #[Route(path: '/image', name: 'image')]
    public function getImage(Request $request)
    {
        try
        {
            $this->pokemon_manager->images();
            return new JsonResponse([
                'message' => "Added image urls successfully"
            ], Response::HTTP_OK);
        }
        catch (\Exception $e)
        {
            return new JsonResponse([
                'message' => "{$e->getMessage()}"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}