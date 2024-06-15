<?php

namespace App\Controller;

use App\Entity\MissingPokemon;
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

    #[Route(path: '/database/add/missing', name: 'add_missing_pokemon')]
    public function addMissingPokemon(Request $request): Response
    {
        foreach (PokemonManager::getScarletVioletMainSeries() as $serie_name)
        {
            $missing_in_serie = $this->pokemon_manager->checkForMissingPokemonInSerie($serie_name);
            $already_in_missing = $this->entity_manager->getRepository(MissingPokemon::class)->findBy(['serie' => $serie_name]);
            if (!empty($missing_in_serie) && \count($missing_in_serie) !== \count($already_in_missing))
            {
                foreach ($missing_in_serie as $serie_nr)
                {
                    $base_url = "https://www.pokellector.com/";
                    $url = $base_url . Pokemon::hyphenate($serie_name) . "-Expansion" . "/" . "Card-" . Pokemon::getSerieNrGallery($serie_name, $serie_nr);
                    $crawler = $this->pokemon_manager->getCrawler($url);
                    $title = $crawler->filterXPath('//meta[@property="og:title"]')->attr('content');
                    $image_url = $crawler->filterXPath('//meta[@property="og:image"]')->attr('content');
                    if ($this->entity_manager->getRepository(MissingPokemon::class)->findOneBy(['title' => $title]) === null)
                    {
                        $missing_pokemon = new MissingPokemon($title, $serie_name, $serie_nr, $image_url);
                        $this->entity_manager->persist($missing_pokemon);
                    }
                    $this->entity_manager->flush();
                }
            }
        }

        return new JsonResponse([
            'message' => 'Done',
        ], Response::HTTP_OK);
    }
}