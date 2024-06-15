<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\ConfigManager;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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

    #[Route(path: '/test', name: 'test')]
    public function test(Request $request)
    {
        try {
            $pikachu = $this->entity_manager->getRepository(Pokemon::class)->findOneBy(['name' => 'Pikachu']);
            $this->pokemon_manager->getFullTitle($pikachu);
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

    #[Route(path: '/', name: 'show_final_pokemon')]
    public function index(Request $request): Response
    {
        $search_string = $request->get('q');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string, true);
        return $this->render('pokemon/index.html.twig',[
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),


            'pokemon' => $pokemon,

            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/all', name: 'show_all_pokemon')]
    public function showAllPokemon(Request $request): Response
    {
        $search_string = $request->get('q');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string);
        return $this->render('pokemon/index.html.twig',[
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),

            'pokemon' => $pokemon,

            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/show/{id}', name: 'show_single_pokemon')]
    public function showSinglePokemon(
        #[MapEntity(mapping: ['id' => 'id'])]
        Pokemon $pokemon
    ): Response
    {
        $show_pokemon = $this->entity_manager->getRepository(Pokemon::class)->findOneBy(
            ['name' => $pokemon->getName(), 'serie' => $pokemon->getSerie(), 'serie_nr' => $pokemon->getSerieNr()]
        );
        return $this->render('pokemon/show.html.twig', [
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),


            'pokemon' => $show_pokemon,
        ]);
    }

    #[Route(path: '/print-missing', name: 'print_missing_serie_numbers')]
    public function getMissing(Request $request)
    {
        try {
            $missing = $this->pokemon_manager->printMissingPokemon();
            return new JsonResponse([
                'total'   => $missing[1],
                'missing' => $missing[0],
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