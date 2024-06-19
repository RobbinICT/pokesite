<?php

namespace App\Controller;

use App\Entity\MissingPokemon;
use App\Entity\MissingUniquePokemon;
use App\Entity\Pokemon;
use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $exclude_paradox_rift = ConfigManager::getExcludeParadoxRiftEnvironmentVariable();
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
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),

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
        $show_pokemon = $this->entity_manager->getRepository(MissingPokemon::class)->findOneBy(['title' => $pokemon->getTitle()]);
        return $this->render('missing_pokemon/show.html.twig', [
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),

            'pokemon' => $show_pokemon,
        ]);
    }

    #[Route(path: '/missing/update', name: 'update_missing')]
    public function updateMissingPokemon(Request $request)
    {
        try {
            $series = $this->entity_manager->getRepository(MissingPokemon::class)->getIncompleteSeries();
            $deleted = [];
            foreach ($series as $serie)
            {
                $missing_pokemon = $this->entity_manager->getRepository(MissingPokemon::class)->findBy(['serie' => $serie['serie']]);
                foreach ($missing_pokemon as $pokemon)
                {
                    if ($this->entity_manager->getRepository(Pokemon::class)->findOneBy(['serie' => $serie['serie'], 'serie_nr' => $pokemon->getSerieNr()]))
                    {
                        $deleted[$serie['serie']][] = $pokemon->getTitle();
                        $this->entity_manager->remove($pokemon);
                    }
                }

                $this->entity_manager->flush();
            }
            if(\count($deleted) === 0)
            {
                $deleted = ['message' => 'No missing pokemon found'];
            }
            return new JsonResponse($deleted, Response::HTTP_OK);
        }
        catch (\Exception $e)
        {
            return new JsonResponse([
                'message' => "{$e->getMessage()}"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/missing/unique', name: 'show_unique_missing_pokemon')]
    public function showUniqueMissingPokemon(Request $request): Response
    {
        $missing = [
            [
                'id' => 154,
                'title' => 'Meganium',
            ],
            [
                'id' => 157,
                'title' => 'Typhlosion',
            ],
            [
                'id' => 254,
                'title' => 'Sceptile',
            ],
            [
                'id' => 346,
                'title' => 'Cradily',
            ],
            [
                'id' => 496,
                'title' => 'Servine',
            ],
            [
                'id' => 697,
                'title' => 'Tyrantrum',
            ],
            [
                'id' => 699,
                'title' => 'Aurorus',
            ],
            [
                'id' => 733,
                'title' => 'Toucannon',
            ],
            [
                'id' => 773,
                'title' => 'Silvally',
            ],
            [
                'id' => 784,
                'title' => 'Kommo-o',
            ],
            [
                'id' => 793,
                'title' => 'Nihilego',
            ],
            [
                'id' => 794,
                'title' => 'Buzzwole',
            ],
            [
                'id' => 795,
                'title' => 'Pheromosa',
            ],
            [
                'id' => 796,
                'title' => 'Xurkitree',
            ],
            [
                'id' => 800,
                'title' => 'Necrozma',
            ],
            [
                'id' => 804,
                'title' => 'Naganadel',
            ],
            [
                'id' => 805,
                'title' => 'Stakataka',
            ],
            [
                'id' => 1018,
                'title' => 'Archaludon',
            ],
            [
                'id' => 1019,
                'title' => 'Hydrapple',
            ],
            [
                'id' => 1024,
                'title' => 'Terapagos',
            ],
            [
                'id' => 1025,
                'title' => 'Pecharunt',
            ],
        ];

        $pokemon = [];
        foreach ($missing as $miss)
        {
            $pokemon[] = new MissingUniquePokemon($miss['id'], $miss['title']);
        }

        if (ConfigManager::getAlphabeticalOrderForUniqueMissingPokemonEnvironmentVariable())
        {
            usort($pokemon, function($a, $b) {
                return strcmp($a->getTitle(), $b->getTitle());
            });
        }

        $total = \count($pokemon);
        return $this->render('missing_pokemon/unique_index.html.twig',[
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            ConfigManager::ENV_VAR_USE_LOCAL_CARDS => ConfigManager::getUseLocalCardsEnvironmentVariable(),

            'pokemon' => $pokemon,
            'total' => $total,
        ]);
    }
}