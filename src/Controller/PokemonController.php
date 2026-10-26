<?php

namespace App\Controller;

use App\Entity\Config;
use App\Entity\Pokemon;
use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PokemonController extends AbstractController
{
    private const BATCH_SIZE = 60;

    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/', name: 'show_final_pokemon')]
    public function index(Request $request): Response
    {
        return $this->renderList($request, true);
    }

    #[Route(path: '/all', name: 'show_all_pokemon')]
    public function showAllPokemon(Request $request): Response
    {
        return $this->renderList($request, false);
    }

    private function renderList(Request $request, bool $only_final): Response
    {
        $search_string = $request->get('q');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)
            ->getPokemon($search_string, $only_final, 0, self::BATCH_SIZE);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();

        return $this->render('pokemon/index.html.twig', [
            'config' => $config,
            'pokemon' => $pokemon,
            'search_string' => $search_string,
            'is_final' => $only_final,
            'batch_size' => self::BATCH_SIZE,
            'has_more' => \count($pokemon) === self::BATCH_SIZE,
        ]);
    }

    #[Route(path: '/api/pokemon', name: 'api_pokemon_batch')]
    public function batch(Request $request): Response
    {
        $search_string = $request->get('q');
        $only_final = $request->query->getBoolean('final');
        $offset = max(0, $request->query->getInt('offset'));

        $pokemon = $this->entity_manager->getRepository(Pokemon::class)
            ->getPokemon($search_string, $only_final, $offset, self::BATCH_SIZE);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();

        $html = $this->renderView('pokemon/_batch.html.twig', [
            'config' => $config,
            'pokemon' => $pokemon,
        ]);

        $response = new Response($html);
        $response->headers->set('X-Has-More', \count($pokemon) === self::BATCH_SIZE ? '1' : '0');

        return $response;
    }

    #[Route(path: '/show/{id}', name: 'show_single_pokemon')]
    public function showSinglePokemon(
        #[MapEntity(mapping: ['id' => 'id'])]
        Pokemon $pokemon
    ): Response
    {
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        $show_pokemon = $this->entity_manager->getRepository(Pokemon::class)->findOneBy(
            ['name' => $pokemon->getName(), 'serie' => $pokemon->getSerie(), 'serie_nr' => $pokemon->getSerieNr()]
        );
        return $this->render('pokemon/show.html.twig', [
            'config' => $config,
            'pokemon' => $show_pokemon,
        ]);
    }
}