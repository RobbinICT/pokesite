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

class DatabaseController extends AbstractController
{
    private EntityManagerInterface $entity_manager;
    private PokemonManager $pokemon_manager;

    public function __construct(EntityManagerInterface $entity_manager, PokemonManager $pokemon_manager)
    {
        $this->entity_manager = $entity_manager;
        $this->pokemon_manager = $pokemon_manager;
    }

    #[Route(path: '/database/clean_import', name: 'import_as_new_database')]
    public function importCvsIntoDatabase(Request $request)
    {
        try {
            $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
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

    #[Route(path: '/database/add', name: 'add_to_database')]
    public function addCsvToDatabase(Request $request)
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
}