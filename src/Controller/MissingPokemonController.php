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
            [ 'id' => 697, 'title' => 'Tyrantrum']
        ];

        $pokemon = [];
        foreach ($missing as $miss)
        {
            $pokemon[] = new MissingUniquePokemon($miss['id'], $miss['title']);
        }

        $missing_alolan = [
            ['Raichu', 'https://www.serebii.net/pokemon/art/026-a.png'],
            ['Sandshrew', 'https://www.serebii.net/pokemon/art/027-a.png'],
            ['Sandslash', 'https://www.serebii.net/pokemon/art/028-a.png'],
            ['Vulpix', 'https://www.serebii.net/pokemon/art/037-a.png'],
            ['Ninetales', 'https://www.serebii.net/pokemon/art/038-a.png'],
            ['Diglett', 'https://www.serebii.net/pokemon/art/050-a.png'],
            ['Dugtrio', 'https://www.serebii.net/pokemon/art/051-a.png'],
            ['Meowth', 'https://www.serebii.net/pokemon/art/052-a.png'],
            ['Persian', 'https://www.serebii.net/pokemon/art/053-a.png'],
            ['Geodude', 'https://www.serebii.net/pokemon/art/074-a.png'],
            ['Graveler', 'https://www.serebii.net/pokemon/art/075-a.png'],
            ['Golem', 'https://www.serebii.net/pokemon/art/076-a.png'],
            ['Muk', 'https://www.serebii.net/pokemon/art/089-a.png'],
            ['Exeggutor', 'https://www.serebii.net/pokemon/art/103-a.png'],
            ['Marowak', 'https://www.serebii.net/pokemon/art/105-a.png'],
        ];

        $missing_galarian = [
            ['Ponyta', 'https://www.serebii.net/pokemon/art/077-g.png'],
            ['Rapidash', 'https://www.serebii.net/pokemon/art/078-g.png'],
            ['Slowbro', 'https://www.serebii.net/pokemon/art/080-g.png'],
            ['Weezing', 'https://www.serebii.net/pokemon/art/110-g.png'],
            ['Slowking', 'https://www.serebii.net/pokemon/art/199-g.png'],
            ['Linoone', 'https://www.serebii.net/pokemon/art/264-g.png'],
            ['Darmanitan', 'https://www.serebii.net/pokemon/art/555-g.png'],
            ['Yamask', 'https://www.serebii.net/pokemon/art/562-g.png'],
        ];

        $missing_hisuian = [
            ['Voltorb', 'https://www.serebii.net/pokemon/art/100-h.png'],
            ['Typhlosion', 'https://www.serebii.net/pokemon/art/157-h.png'],
            ['Samurott', 'https://www.serebii.net/pokemon/art/503-h.png'],
            ['Zoroark', 'https://www.serebii.net/pokemon/art/571-h.png'],
            ['Braviary', 'https://www.serebii.net/pokemon/art/628-h.png'],
            ['Sliggoo', 'https://www.serebii.net/pokemon/art/705-h.png'],
            ['Goodra', 'https://www.serebii.net/pokemon/art/706-h.png'],
            ['Avalugg', 'https://www.serebii.net/pokemon/art/713-h.png'],
            ['Decidueye', 'https://www.serebii.net/pokemon/art/724-h.png'],
        ];

        /** @var Config $config */
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        if ($config->getIsInAlphabeticalOrder())
        {
            usort($pokemon, function($a, $b) {
                return strcmp($a->getTitle(), $b->getTitle());
            });
        }

        $total = \count($missing_alolan) + \count($missing_galarian) + \count($missing_hisuian);
        return $this->render('missing_pokemon/unique_index.html.twig',[
            'config' => $config,
            'pokemon' => $pokemon,
            'total' => $total,
            'missing_alolan' => $missing_alolan,
            'missing_galarian' => $missing_galarian,
            'missing_hisuian' => $missing_hisuian,
        ]);
    }
}
