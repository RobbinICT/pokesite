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

    public function __construct(LoggerInterface $logger, KernelInterface $kernel, EntityManagerInterface $entity_manger)
    {
        $this->logger = $logger;
        $this->kernel = $kernel;
        $this->entity_manager = $entity_manger;
    }

    public function import()
    {
        $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();

        // Get the base directory of your Symfony project
        $base_dir = $this->kernel->getProjectDir();
        // Specify the relative path to the CSV file
        $path = $base_dir . '/var/card.csv';

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

    private function setImageHostingUrl(Pokemon $pokemon)
    {
        // Initialize the HTTP client
        $client = HttpClient::create();

        // Make a GET request to fetch the image
        $url = $pokemon->generatePokellectorUrl();
        if (!$url)
        {
            return false;
        }
        $this->logger->info($pokemon->getName().$pokemon->getSerie().$pokemon->getSerieNr());
        $response = $client->request('GET', $url);
        $content = $response->getContent();

        // Create a new crawler instance and load the HTML content
        $crawler = new Crawler($content);

        // Extract the content of the og:image meta tag
        $image_url = $crawler->filterXPath('//meta[@property="og:image"]')->attr('content');
        if (!str_contains($image_url, strtok($pokemon->getCleanName(), ' ')))
        {
            throw new \Exception(
                "Invalid set:
                Serie:{$pokemon->getSerie()}
                Pokemon: {$pokemon->getName()}
                Pokellector: $url
                Image: $image_url"
            );
        }
        $pokemon->setUrl($image_url);
        $this->entity_manager->persist($pokemon);
    }

    private function extractNumericPart(string $value)
    {
        return (int)preg_replace('/[^0-9]/', '', $value);
    }
}