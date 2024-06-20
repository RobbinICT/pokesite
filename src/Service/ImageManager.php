<?php

namespace App\Service;

use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class ImageManager
{
    private EntityManagerInterface $entity_manager;
    private KernelInterface $kernel;
    public function __construct(EntityManagerInterface $entity_manager, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->kernel = $kernel;
    }

    public function addImageUrls(): int
    {
        $repo = $this->entity_manager->getRepository(Pokemon::class);
        $batch_size = 20;
        $added = 0;

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
                $added++;
            }
            $this->entity_manager->flush();
            sleep(1);
        }
        $this->entity_manager->flush();
        return $added;
    }

    public function downloadImages(): ?array
    {
        $pokemon_list = $this->entity_manager->getRepository(Pokemon::class)->getPokemon();
        $path = $this->kernel->getProjectDir() . '/public/images/pokemon/';
        $failed = [];

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
        }

        if (!empty($failed))
        {
            return $failed;
        }

        return null;
    }

    public function downloadImagesMissingPokemon()
    {
        $pokemon_list = $this->entity_manager->getRepository(MissingPokemon::class)->findAll();
        $path = $this->kernel->getProjectDir() . '/public/images/missing-pokemon/';
        $failed = [];

        /** @var MissingPokemon $pokemon */
        foreach ($pokemon_list as $pokemon) {
            $filename = $pokemon->getTitle() . '.png';

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
                $failed[] = $pokemon->getTitle();
                continue;
            }
            file_put_contents($path . $filename, $image_content);
        }

        if (!empty($failed))
        {
            return $failed;
        }

        return null;
    }

    private function setImageHostingUrl(Pokemon $pokemon)
    {
        $url = $pokemon->generatePokellectorUrl();
        if (!$url)
        {
            return false;
        }

        $client = HttpClient::create();
        $response = $client->request('GET', $url);
        $content = $response->getContent();
        $crawler = new Crawler($content);
        $image_url = $crawler->filterXPath('//meta[@property="og:image"]')->attr('content');
        if (!str_contains($image_url, strtok($pokemon->getCleanName(), ' ')))
        {
            throw new \Exception(
                "Invalid:\n " .
                "Serie:{$pokemon->getSerie()} ({$pokemon->getSerieNr()})\n " .
                "Pokemon: {$pokemon->getName()}\n " .
                "Pokellector: $url\n " .
                "Image: $image_url"
            );
        }
        $pokemon->setUrl($image_url);
        $this->entity_manager->persist($pokemon);
    }
}