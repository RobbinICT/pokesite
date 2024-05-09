<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use function count;

class PokemonController extends AbstractController
{
    private EntityManagerInterface $entity_manager;
    private PokemonManager $pokemon_manager;
    private KernelInterface $kernel;

    public function __construct(EntityManagerInterface $entity_manager, PokemonManager $pokemon_manager, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->pokemon_manager = $pokemon_manager;
        $this->kernel = $kernel;
    }

    #[Route(path: '/', name: 'pokemon_index')]
    public function index(Request $request): Response
    {
        $search_string = $request->get('search_term');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string, true);
        return $this->render('pokemon/index.html.twig',[
            'local_cards' => $_ENV['USE_LOCAL_CARD'],

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
            'local_cards' => $_ENV['USE_LOCAL_CARD'],

            'pokemon' => $show_pokemon,
        ]);
    }

    #[Route(path: '/all', name: 'show_all_pokemon')]
    public function showAllPokemon(Request $request): Response
    {
        $search_string = $request->get('search_term');
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemon($search_string);
        return $this->render('pokemon/index.html.twig',[
            'local_cards' => $_ENV['USE_LOCAL_CARD'],

            'pokemon' => $pokemon,

            'search_string' => $search_string,
        ]);
    }

    #[Route(path: '/get_final_list', name: 'final')]
    public function getFinalList(Request $request): JsonResponse
    {
        $final_list = $this->entity_manager->getRepository(Pokemon::class)->getPokemon(only_show_final_list: true);
        return new JsonResponse($final_list);
    }

    #[Route(path: '/database/clean_import', name: 'import_as_new_database', methods: ['POST'])]
    public function importCvsIntoDatabase(Request $request)
    {
        try {
            $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
            $this->pokemon_manager->import();
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'Imported successfully'
        ], Response::HTTP_OK);
    }

    #[Route(path: '/database/add', name: 'add_to_database', methods: ['POST'])]
    public function addCsvToDatabase(Request $request)
    {
        try {
            $this->pokemon_manager->import();
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'Imported successfully'
        ], Response::HTTP_OK);
    }

    #[Route(path: '/add/image_urls', name: 'add_image_urls')]
    public function getImage(Request $request)
    {
        try {
            $this->pokemon_manager->images();
            return new JsonResponse([
                'message' => "Added image urls successfully"
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => "{$e->getMessage()}"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/validate/url', name: 'validate')]
    public function validate(Request $request)
    {
        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong = [];
        foreach ($all as $pokemon) {
            if ($pokemon->getUrl() !== 'TODO') {
                if (!str_contains($pokemon->getUrl(), strtok($pokemon->getCleanName(), ' '))) {
                    $wrong[$pokemon->getUrl()] = $pokemon->getCleanName();
                }
            }
        }
        return new JsonResponse(['count' => count($wrong), 'wrong' => $wrong]);
    }

    #[Route(path: '/download/images', name: 'download_final_list')]
    public function downloadImages(Request $request)
    {
        $pokemon_list = $this->entity_manager->getRepository(Pokemon::class)->getPokemon();
        $path = $this->kernel->getProjectDir() . '/public/images/pokemon/';
        $failed = [];
        $new_images = 0;

        /** @var Pokemon $pokemon */
        foreach ($pokemon_list as $pokemon) {
            $filename = $pokemon->getUniqueIdentifier() . '.png';

            // Check if the file already exists
            if (file_exists($path . $filename)) {
                continue;
            }

            $url = $pokemon->getUrl();

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $image_content = file_get_contents($url);
            if ($image_content === false) {
                $failed[] = $pokemon->getName();
                continue;
            }
            file_put_contents($path . $filename, $image_content);
            $new_images++;
        }

        if (\count($failed) > 0)
        {
            return new JsonResponse(['Count' => \count($failed), 'Pokemon' => $failed]);
        }
        return new JsonResponse("$new_images new images downloaded");
    }
}