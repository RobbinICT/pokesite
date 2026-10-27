<?php

namespace App\Command;

use App\Service\ImageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console images:add:urls-download
 */
class AddImagesCommand extends Command
{
    use ReportsProgress;

    private ImageManager $image_manager;

    public function __construct(ImageManager $image_manager)
    {
        parent::__construct(self::getDefaultName());
        $this->image_manager = $image_manager;
    }

    public static function getDefaultName(): string
    {
        return 'images:add:urls-download';
    }

    public function configure(): void
    {
        $this->setDescription('Add image urls and download the images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Looking up image URLs on Pokéllector for cards without one');
        $progress = $this->progressReporter($output);
        $report = $this->image_manager->addImageUrls($progress);
        $io->writeln(sprintf('Added %d image URL(s)', $report['added']));
        foreach ($report['failed'] as $card => $reason)
        {
            $io->writeln(sprintf('No URL for %s: %s', $card, $reason));
        }

        $io->note('Downloading card images that are not on the server yet');
        $report = $this->image_manager->downloadImages($progress);
        $io->writeln(sprintf('Downloaded %d card image(s)', $report['downloaded']));
        foreach ($report['failed'] as $card => $reason)
        {
            $io->writeln(sprintf('Failed %s: %s', $card, $reason));
        }

        $io->note('Downloading missing pokemon images');
        $response = $this->image_manager->downloadImagesMissingPokemon();
        if ($response !== null)
        {
            $io->error('Downloading missing pokemon images failed');
            $io->error($response);
        }

        $io->success('Done updating image urls and downloading images');

        return 0;
    }
}