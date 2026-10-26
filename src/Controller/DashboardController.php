<?php

namespace App\Controller;

use App\Entity\Config;
use App\Entity\Minifigure;
use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/', name: 'home')]
    public function index(): Response
    {
        /** @var Config $config */
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        $pokemon_repository = $this->entity_manager->getRepository(Pokemon::class);

        return $this->render('dashboard.html.twig', [
            'config' => $config,
            'pokemon_stats' => [
                'owned' => $pokemon_repository->countPokemon(),
                'binder' => $pokemon_repository->countPokemon(true),
                'missing' => $this->entity_manager->getRepository(MissingPokemon::class)
                    ->countMissingPokemon(null, $config->getIsParadoxRiftExclude()),
                'missing_unique' => MissingPokemonController::countMissingUnique(),
            ],
            'lego_stats' => [
                'owned' => $this->entity_manager->getRepository(Minifigure::class)->count([]),
            ],
        ]);
    }
}
