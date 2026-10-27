<?php

namespace App\Service;

use App\Admin\Type\PokemonCardType;
use App\Entity\MissingPokemon;
use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class ImageManager
{
    private EntityManagerInterface $entity_manager;
    private KernelInterface $kernel;
    private CollectableImageStorage $image_storage;
    private PokemonCardType $card_type;

    public function __construct(EntityManagerInterface $entity_manager, KernelInterface $kernel, CollectableImageStorage $image_storage, PokemonCardType $card_type)
    {
        $this->entity_manager = $entity_manager;
        $this->kernel = $kernel;
        $this->image_storage = $image_storage;
        $this->card_type = $card_type;
    }

    /**
     * Looks up image URLs on Pokéllector for cards without one. Each card is
     * tried once; a card that can't be matched is reported and skipped, so one
     * bad card doesn't stop the rest.
     *
     * @return array{added: int, failed: array<string, string>} failed: card => reason
     */
    public function addImageUrls(): array
    {
        $cards = $this->entity_manager->getRepository(Pokemon::class)->getBatchOfPokemonWithoutURL();
        $report = ['added' => 0, 'failed' => []];

        /** @var Pokemon $pokemon */
        foreach ($cards as $i => $pokemon)
        {
            $label = sprintf('%s (%s %s)', $pokemon->getName(), $pokemon->getSerie(), $pokemon->getSerieNr());
            try
            {
                if ($this->setImageHostingUrl($pokemon) === false)
                {
                    $report['failed'][$label] = 'no Pokéllector page for this serie';
                    continue;
                }
                $report['added']++;
            }
            catch (\Throwable $e)
            {
                $report['failed'][$label] = mb_strimwidth(trim(preg_replace('/\s+/', ' ', $e->getMessage())), 0, 200, '…');
            }

            // Save and pause every 20 cards, to go easy on Pokéllector.
            if (($i + 1) % 20 === 0)
            {
                $this->entity_manager->flush();
                sleep(1);
            }
        }
        $this->entity_manager->flush();

        return $report;
    }

    /**
     * Downloads card images that are not on the server yet.
     *
     * @return array{downloaded: int, failed: array<string, string>} failed: card => reason
     */
    public function downloadImages(): array
    {
        $report = ['downloaded' => 0, 'failed' => []];

        /** @var Pokemon $pokemon */
        foreach ($this->entity_manager->getRepository(Pokemon::class)->findAll() as $pokemon)
        {
            $result = $this->image_storage->downloadMissing($this->card_type, $pokemon);
            if ($result === true)
            {
                $report['downloaded']++;
            }
            elseif (\is_string($result))
            {
                $report['failed'][sprintf('%s (%s %s)', $pokemon->getName(), $pokemon->getSerie(), $pokemon->getSerieNr())] = $result;
            }
        }

        return $report;
    }

    public function downloadImagesMissingPokemon()
    {
        $pokemon_list = $this->entity_manager->getRepository(MissingPokemon::class)->findAll();
        $path = $this->kernel->getProjectDir() . '/public/images/missing-pokemon/';
        $failed = [];
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            return ['the images/missing-pokemon folder is not writable'];
        }

        /** @var MissingPokemon $pokemon */
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

            $image_content = @file_get_contents($url);
            if ($image_content === false || @file_put_contents($path . $filename, $image_content) === false) {
                $failed[] = $pokemon->getTitle();
            }
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