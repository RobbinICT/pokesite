<?php

namespace App\Controller;

use App\Entity\Pokemon;
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
        $search_string = $request->get('search_term');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string, true);
        return $this->render('pokemon/index.html.twig',[
            'local_cards' => $_ENV['USE_LOCAL_CARD'] ?? true,
            'super_admin' => $_ENV['SUPER_ADMIN'] ?? false,

            'pokemon' => $pokemon,

            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/show/{id}', name: 'show_pokemon')]
    public function show_pokemon(
        #[MapEntity(mapping: ['id' => 'id'])]
        Pokemon $pokemon
    ): Response
    {
        $show_pokemon = $this->entity_manager->getRepository(Pokemon::class)->findOneBy(
            ['name' => $pokemon->getName(), 'serie' => $pokemon->getSerie(), 'serie_nr' => $pokemon->getSerieNr()]
        );
        return $this->render('pokemon/show.html.twig', [
            'local_cards' => $_ENV['USE_LOCAL_CARD'] ?? true,
            'super_admin' => $_ENV['SUPER_ADMIN'] ?? false,

            'pokemon' => $show_pokemon,
        ]);
    }

    #[Route(path: '/all', name: 'show_all_pokemon')]
    public function showAllPokemon(Request $request): Response
    {
        $search_string = $request->get('search_term');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string);
        return $this->render('pokemon/index.html.twig',[
            'local_cards' => $_ENV['USE_LOCAL_CARD'] ?? true,
            'super_admin' => $_ENV['SUPER_ADMIN'] ?? false,

            'pokemon' => $pokemon,

            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/missing', name: 'print_missing_serie_numbers')]
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