<?php

namespace App\Service;

use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class DatabaseManager
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

    public function importCsvFile(string $delimiter = ';')
    {
        // Get the base directory of your Symfony project
        $base_dir = $this->kernel->getProjectDir();
        // Specify the relative path to the CSV file
        $path = $base_dir . '/var/cards.csv';

        // Create a CSV reader instance
        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        // Iterate through each row
        foreach ($csv as $row) {
            $serie_nr = $this->extractNumericPart($row['SerieNr']);
            $pokemon = new Pokemon((int)$row['Ndex'], $row['Name'], (int)$row['Gen'], $row['Serie'], $serie_nr, $row['ListNr']);
            $pokemon->setUrl($row['Url']);
            $this->entity_manager->persist($pokemon);
        }

        $this->entity_manager->flush();
    }

    public function addMissingPokemon(): array
    {
        $added = [];
        foreach (PokemonManager::getScarletVioletMainSeries() as $serie_name)
        {
            $missing_in_serie = $this->pokemon_manager->checkForMissingPokemonInSerie($serie_name);
            if (!empty($missing_in_serie))
            {
                foreach ($missing_in_serie as $serie_nr)
                {
                    if ($this->entity_manager->getRepository(MissingPokemon::class)->findOneBy(['serie' => $serie_name, 'serie_nr' => $serie_nr]) === null)
                    {
                        $base_url = "https://www.pokellector.com/";
                        $url = $base_url . Pokemon::hyphenate($serie_name) . "-Expansion" . "/" . "Card-" . Pokemon::getSerieNrGallery($serie_name, $serie_nr);
                        $client = HttpClient::create();
                        $response = $client->request('GET', $url);
                        $content = $response->getContent();
                        $crawler = new Crawler($content);
                        $title = $crawler->filterXPath('//meta[@property="og:title"]')->attr('content');
                        $image_url = $crawler->filterXPath('//meta[@property="og:image"]')->attr('content');
                        $missing_pokemon = new MissingPokemon($title, $serie_name, $serie_nr, $image_url);
                        $this->entity_manager->persist($missing_pokemon);
                        $added[$serie_name][] = $missing_pokemon->getTitle();
                    }
                }
                $this->entity_manager->flush();
            }
        }

        return $added;
    }

    public function removeAcquiredMissingPokemon(): array
    {
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

        return $deleted;
    }

    private function extractNumericPart(string $value): int
    {
        return (int)preg_replace('/[^0-9]/', '', $value);
    }
}