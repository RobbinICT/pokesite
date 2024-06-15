<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class ImageController extends AbstractController
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

    #[Route(path: '/images/urls', name: 'add_image_urls')]
    public function getImage(Request $request)
    {
        try {
            $this->pokemon_manager->images();
            return new JsonResponse([
                'message' => "Added image urls successfully"
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => "{$e->getMessage()}"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/images/download', name: 'download_images')]
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
        return new JsonResponse([
            'message' => "$new_images new images downloaded"
        ], Response::HTTP_OK);
    }
}