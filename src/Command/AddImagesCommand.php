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
        $io->note('Adding image urls');
        $added = $this->image_manager->addImageUrls();
        if ($added > 0)
        {
            $io->success("Added $added image urls");
        }

        $io->note('Downloading images');
        $response = $this->image_manager->downloadImages();
        if ($response !== null)
        {
            $io->error('Downloading images failed');
            $io->error($response);
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