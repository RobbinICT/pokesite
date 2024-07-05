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
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/', name: 'show_final_pokemon')]
    public function index(Request $request): Response
    {
        $search_string = $request->get('q');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string, true);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        return $this->render('pokemon/index.html.twig',[
            'config' => $config,
            'pokemon' => $pokemon,
            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/all', name: 'show_all_pokemon')]
    public function showAllPokemon(Request $request): Response
    {
        $search_string = $request->get('q');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        return $this->render('pokemon/index.html.twig',[
            'config' => $config,
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