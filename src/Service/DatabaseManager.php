<?php

namespace App\Service;

use App\Entity\Minifigure;
use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class DatabaseManager
{
    /** Column headers importCsvFile() expects in cards.csv */
    public const CARDS_CSV_COLUMNS = ['Ndex', 'Name', 'Gen', 'Serie', 'SerieNr', 'ListNr', 'Url'];
    /** Required column headers for minifigures.csv; an optional Url column is used when present. */
    public const MINIFIGURES_CSV_COLUMNS = ['Identifier', 'Series', 'Name'];

    private EntityManagerInterface $entity_manager;
    private PokemonManager $pokemon_manager;
    private KernelInterface $kernel;

    public function __construct(EntityManagerInterface $entity_manager, PokemonManager $pokemon_manager, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->pokemon_manager = $pokemon_manager;
        $this->kernel = $kernel;
    }

    /**
     * @return int number of imported cards
     */
    public function importCsvFile(string $delimiter = ';'): int
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
        $imported = 0;
        foreach ($csv as $row) {
            $serie_nr = $this->extractNumericPart($row['SerieNr']);
            $pokemon = new Pokemon((int)$row['Ndex'], $row['Name'], (int)$row['Gen'], $row['Serie'], $serie_nr, $row['ListNr']);
            $pokemon->setUrl($row['Url']);
            $this->entity_manager->persist($pokemon);
            $imported++;
        }

        $this->entity_manager->flush();

        return $imported;
    }

    /**
     * Imports var/minifigures.csv. Url is optional (falls back to the BrickLink
     * image); an empty Identifier becomes a temporary one ("temp-venom").
     * Identifiers that already exist are skipped, so re-importing is safe.
     *
     * @return array{imported: int, skipped: string[]}
     */
    public function importMinifiguresCsv(string $delimiter = ';'): array
    {
        $base_dir = $this->kernel->getProjectDir();
        $path = $base_dir . '/var/minifigures.csv';

        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        // Lowercased, like the admin's duplicate check.
        $existing = [];
        foreach ($this->entity_manager->getRepository(Minifigure::class)->getIdentifiers() as $identifier) {
            $existing[strtolower($identifier)] = true;
        }

        $report = ['imported' => 0, 'skipped' => []];
        foreach ($csv as $row) {
            $name = trim($row['Name'] ?? '');
            if ($name === '') {
                continue;
            }
            $identifier = trim($row['Identifier'] ?? '');
            if ($identifier === '') {
                $identifier = Minifigure::TEMPORARY_PREFIX . Minifigure::slugify($name);
            }
            if (isset($existing[strtolower($identifier)])) {
                $report['skipped'][] = $identifier;
                continue;
            }
            $existing[strtolower($identifier)] = true;

            $minifigure = new Minifigure($identifier, trim($row['Series'] ?? ''), $name);
            $url = trim($row['Url'] ?? '');
            $minifigure->setUrl($url !== '' ? $url : $minifigure->getBricklinkImageUrl());
            $this->entity_manager->persist($minifigure);
            $report['imported']++;
        }

        $this->entity_manager->flush();

        return $report;
    }

    /**
     * Fills in missing image URLs for minifigures with BrickLink's catalog
     * image (img.bricklink.com/ItemImage/MN/0/<identifier>.png). Each URL is
     * checked first so unknown identifiers don't get a broken link.
     * Returns a report keyed by outcome.
     */
    public function fillMinifigureImageUrls(): array
    {
        $report = ['updated' => [], 'failed' => []];
        $minifigures = $this->entity_manager->getRepository(Minifigure::class)->getMinifiguresWithoutUrl();
        $client = HttpClient::create([
            'headers' => [
                // BrickLink blocks non-browser clients; present a browser UA.
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            ],
        ]);

        foreach ($minifigures as $minifigure) {
            $url = $minifigure->getBricklinkImageUrl();
            if ($url === null) {
                continue;
            }
            try {
                $response = $client->request('HEAD', $url);
                $content_type = $response->getHeaders(false)['content-type'][0] ?? '';
                if ($response->getStatusCode() !== 200 || !str_starts_with($content_type, 'image/')) {
                    $report['failed'][] = $minifigure->getIdentifier();
                    continue;
                }
                $minifigure->setUrl($url);
                $report['updated'][] = $minifigure->getIdentifier();
            } catch (\Throwable $e) {
                $report['failed'][] = $minifigure->getIdentifier();
            }
        }

        $this->entity_manager->flush();

        return $report;
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