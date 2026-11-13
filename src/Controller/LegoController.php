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

    /**
     * The collection grouped by series, with the wishlist figures of each
     * series shown faded in between.
     */
    #[Route(path: '/lego/series', name: 'lego_series')]
    public function series(Request $request): Response
    {
        return $this->renderGrouped($request, 'lego/series.html.twig', [false, true]);
    }

    /** Figures you want but don't have yet, grouped by series. Kept up to date by hand in the admin. */
    #[Route(path: '/lego/wishlist', name: 'lego_wishlist')]
    public function wishlist(Request $request): Response
    {
        return $this->renderGrouped($request, 'lego/wishlist.html.twig', [true]);
    }

    /**
     * @param bool[] $wanted which lists to show: false = collection, true = wishlist
     */
    private function renderGrouped(Request $request, string $template, array $wanted): Response
    {
        $search_string = $request->get('q');
        $repository = $this->entity_manager->getRepository(Minifigure::class);

        // Every figure at once: anchors to a series need the whole page.
        $groups = [];
        foreach ($wanted as $is_wanted) {
            foreach ($repository->getMinifigures($search_string, wanted: $is_wanted) as $minifigure) {
                $groups[$minifigure->getSeries()][] = $minifigure;
            }
        }
        ksort($groups, \SORT_NATURAL | \SORT_FLAG_CASE);
        // Owned first within a series, then by identifier like the overview.
        foreach ($groups as &$minifigures) {
            usort($minifigures, fn (Minifigure $a, Minifigure $b) => [$a->isWanted(), $a->getIdentifier()] <=> [$b->isWanted(), $b->getIdentifier()]);
        }
        unset($minifigures);

        return $this->render($template, [
            'config' => $this->entity_manager->getRepository(Config::class)->getConfig(),
            'groups' => $groups,
            'search_string' => $search_string,
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
