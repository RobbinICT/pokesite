<?php

namespace App\Controller;

use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use App\Service\ConfigManager;
use App\Service\PokemonManager;
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
        $search_string = $request->get('search_term');
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
}