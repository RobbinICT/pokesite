<?php

namespace App\Controller;

use App\Entity\Config;
use App\Entity\Minifigure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegoController extends AbstractController
{
    private const BATCH_SIZE = 60;

    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/lego', name: 'lego_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('lego_overview');
    }

    #[Route(path: '/lego/overview', name: 'lego_overview')]
    public function overview(Request $request): Response
    {
        $search_string = $request->get('q');
        $minifigures = $this->entity_manager->getRepository(Minifigure::class)
            ->getMinifigures($search_string, 0, self::BATCH_SIZE);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();

        return $this->render('lego/index.html.twig', [
            'config' => $config,
            'minifigures' => $minifigures,
            'search_string' => $search_string,
            'batch_size' => self::BATCH_SIZE,
            'has_more' => \count($minifigures) === self::BATCH_SIZE,
        ]);
    }

    #[Route(path: '/api/lego', name: 'api_lego_batch')]
    public function batch(Request $request): Response
    {
        $search_string = $request->get('q');
        $offset = max(0, $request->query->getInt('offset'));

        $minifigures = $this->entity_manager->getRepository(Minifigure::class)
            ->getMinifigures($search_string, $offset, self::BATCH_SIZE);
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();

        $html = $this->renderView('lego/_batch.html.twig', [
            'config' => $config,
            'minifigures' => $minifigures,
        ]);

        $response = new Response($html);
        $response->headers->set('X-Has-More', \count($minifigures) === self::BATCH_SIZE ? '1' : '0');

        return $response;
    }
}
