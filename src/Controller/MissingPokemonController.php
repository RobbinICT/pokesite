<?php

namespace App\Controller;

use App\Entity\Config;
use App\Entity\MissingPokemon;
use App\Entity\MissingUniquePokemon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MissingPokemonController extends AbstractController
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/missing', name: 'show_missing_pokemon')]
    public function index(Request $request): Response
    {
        $search_string = $request->get('q');
        /** @var Config $config */
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        $exclude_paradox_rift = $config->getIsParadoxRiftExclude();
        $missing_pokemon_list = $this->entity_manager->getRepository(MissingPokemon::class)->findAllMissingPokemon($search_string, $exclude_paradox_rift);
        $total = \count($missing_pokemon_list);

        $grouped_pokemon = [];

        foreach ($missing_pokemon_list as $pokemon)
        {
            $serie = $pokemon->getSerie();
            if (!isset($grouped_pokemon[$serie]))
            {
                $grouped_pokemon[$serie] = [];
            }
            $grouped_pokemon[$serie][] = $pokemon;
        }

        return $this->render('missing_pokemon/index.html.twig',[
            'config' => $config,
            'pokemon' => $grouped_pokemon,
            'search_string' => $search_string,
            'total' => $total,
        ]);
    }

    #[Route(path: '/missing/show/{id}', name: 'show_single_missing_pokemon')]
    public function showSinglePokemon(
        #[MapEntity(mapping: ['id' => 'id'])]
        MissingPokemon $pokemon
    ): Response
    {
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        $show_pokemon = $this->entity_manager->getRepository(MissingPokemon::class)->findOneBy(['title' => $pokemon->getTitle()]);
        return $this->render('missing_pokemon/show.html.twig', [
            'config' => $config,
            'pokemon' => $show_pokemon,
        ]);
    }

    #[Route(path: '/missing/unique', name: 'show_unique_missing_pokemon')]
    public function showUniqueMissingPokemon(Request $request): Response
    {
        $missing = [
            [
                'id' => 697,
                'title' => 'Tyrantrum',
            ],
        ];

        $pokemon = [];
        foreach ($missing as $miss)
        {
            $pokemon[] = new MissingUniquePokemon($miss['id'], $miss['title']);
        }

        /** @var Config $config */
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        if ($config->getIsInAlphabeticalOrder())
        {
            usort($pokemon, function($a, $b) {
                return strcmp($a->getTitle(), $b->getTitle());
            });
        }

        $total = \count($pokemon);
        return $this->render('missing_pokemon/unique_index.html.twig',[
            'config' => $config,
            'pokemon' => $pokemon,
            'total' => $total,
        ]);
    }
}
