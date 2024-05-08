<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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

    #[Route(path: '/', name: 'pokemon_list')]
    public function index(Request $request): Response
    {
//        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getPokemonByGen(1, true);
        $pokemon = $this->entity_manager->getRepository(Pokemon::class)->getFinalList();
        return $this->render('pokemon/index.html.twig',[
            'pokemon' => $pokemon,
        ]);
    }

    #[Route(path: '/get_final_list', name: 'final')]
    public function getFinalList(Request $request): JsonResponse
    {
        $final_list = $this->entity_manager->getRepository(Pokemon::class)->getFinalList();
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

    #[Route(path: '/download/final_list', name: 'download_final_list')]
    public function downloadImages(Request $request)
    {

        // TODO add body so changed pokemon can be deleted first and downloaded again

        $finals = $this->entity_manager->getRepository(Pokemon::class)->getFinalList();
        $path = $this->kernel->getProjectDir() . '/public/images/';
        $failed = [];

        /** @var Pokemon $pokemon */
        foreach ($finals as $pokemon) {
            $filename = $pokemon->getName() . '.png';

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
        }

        if (\count($failed) > 0)
        {
            return new JsonResponse(['Count' => \count($failed), 'Pokemon' => $failed]);
        }
        return new JsonResponse("All images downloaded");
    }
}