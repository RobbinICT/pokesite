<?php

namespace App\Service;

use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class PokemonManager
{
    private LoggerInterface $logger;
    private KernelInterface $kernel;
    private EntityManagerInterface $entity_manager;

    public const SERIE_SV_SCARLET_VIOLET = 'Scarlet & Violet';
    public const SERIE_SV_PALDEA_EVOLVED = 'Paldea Evolved';
    public const SERIE_SV_OBSIDIAN_FLAMES = 'Obsidian Flames';
    public const SERIE_SV_PARADOX_RIFT = 'Paradox Rift';
    public const SERIE_SV_TEMPORAL_FORCES = 'Temporal Forces';
    public const SERIE_SV_TWILIGHT_MASQUERADE = 'Twilight Masquerade';

    public function __construct(LoggerInterface $logger, KernelInterface $kernel, EntityManagerInterface $entity_manger)
    {
        $this->logger = $logger;
        $this->kernel = $kernel;
        $this->entity_manager = $entity_manger;
    }

    public static function getScarletVioletMainSeries()
    {
        return [
            PokemonManager::SERIE_SV_SCARLET_VIOLET,
            PokemonManager::SERIE_SV_PALDEA_EVOLVED,
            PokemonManager::SERIE_SV_OBSIDIAN_FLAMES,
            PokemonManager::SERIE_SV_PARADOX_RIFT,
            PokemonManager::SERIE_SV_TEMPORAL_FORCES,
            PokemonManager::SERIE_SV_TWILIGHT_MASQUERADE
        ];
    }

    public function import()
    {
        // Get the base directory of your Symfony project
        $base_dir = $this->kernel->getProjectDir();
        // Specify the relative path to the CSV file
        $path = $base_dir . '/var/cards.csv';

        // Create a CSV reader instance
        $csv = Reader::createFromPath($path, 'r');
        $csv->setDelimiter(';');
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

    public function images()
    {
        $repo = $this->entity_manager->getRepository(Pokemon::class);
        $batch_size = 20;

        $has_next_batch = true;
        while ($has_next_batch)
        {
            $batch = $repo->getBatchOfPokemonWithoutURL($batch_size);
            if (\count($batch) < $batch_size) {
                $has_next_batch = false;
            }

            /** @var Pokemon $pokemon */
            foreach ($batch as $pokemon)
            {
                $this->setImageHostingUrl($pokemon);
            }
            $this->entity_manager->flush();
            $this->logger->info('Flushing');
            sleep(1);
        }
        $this->entity_manager->flush();

        return new JsonResponse([
            'message' => "Successfully set images for pokemon"
        ], Response::HTTP_OK);
    }

    public function setImageHostingUrl(Pokemon $pokemon)
    {
        $url = $pokemon->generatePokellectorUrl();
        if (!$url)
        {
            return false;
        }

        $crawler = $this->getCrawler($url);
        $image_url = $crawler->filterXPath('//meta[@property="og:image"]')->attr('content');
        if (!str_contains($image_url, strtok($pokemon->getCleanName(), ' ')))
        {
            throw new \Exception(
                "Invalid:\n " .
                "Serie:{$pokemon->getSerie()}\n ({$pokemon->getSerieNr()})" .
                "Pokemon: {$pokemon->getName()}\n " .
                "Pokellector: $url\n " .
                "Image: $image_url"
            );
        }
        $pokemon->setUrl($image_url);
        $this->entity_manager->persist($pokemon);
    }

    public function getFullTitle(Pokemon $pokemon): ?string
    {
        $url = $pokemon->generatePokellectorUrl();
        if (!$url)
        {
            return null;
        }
        $crawler = $this->getCrawler($url);
        $title = $crawler->filterXPath('//meta[@property="og:title"]')->attr('content');
        return $title;
    }

    public function getCrawler($url)
    {
        $this->logger->info($url);
        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        $content = $response->getContent();
        return new Crawler($content);
    }

    public function printMissingPokemon()
    {
        $count = 0;
        $missing = [];
        foreach (self::getScarletVioletMainSeries() as $serie_name)
        {
            $missing_in_serie = $this->checkForMissingPokemonInSerie($serie_name);

            if (!empty($missing_in_serie))
            {
                foreach ($missing_in_serie as $serie_nr)
                {
                    $base_url = "https://www.pokellector.com/";
                    $url = $base_url . Pokemon::hyphenate($serie_name) . "-Expansion" . "/" . "Card-" . Pokemon::getSerieNrGallery($serie_name, $serie_nr);
                    $crawler = $this->getCrawler($url);
                    $title = $crawler->filterXPath('//meta[@property="og:title"]')->attr('content');
                    $missing[$serie_name][] = $title;
                }

                $count += \count($missing_in_serie);
            }
        }

        return [$missing, $count];
    }

    public function checkForMissingPokemonInSerie(string $serie_name): array
    {
        $my_numbers = array_column($this->entity_manager->getRepository(Pokemon::class)->getPokemonSerieNumbersBySerie($serie_name), 'serie_nr');
        $limit = self::getLimitOfSerie($serie_name);
        $all_numbers = range(1, $limit);
        $missing_numbers = array_diff($all_numbers, $my_numbers);
        return array_splice($missing_numbers, 0);
    }

    private function extractNumericPart(string $value)
    {
        return (int)preg_replace('/[^0-9]/', '', $value);
    }

    public static function getLimitOfSerie(string $serie_name)
    {
        switch ($serie_name)
        {
            case 'Scarlet & Violet':
                return 165;
            case 'Paldea Evolved':
                return 170;
            case 'Obsidian Flames':
                return 185;
            case 'Paradox Rift':
                return 158;
            case 'Temporal Forces':
                return 139;
            case 'Twilight Masquerade':
                return 140;
        }
    }
}