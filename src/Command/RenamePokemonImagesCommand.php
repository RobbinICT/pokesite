<?php

namespace App\Command;

use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @example php bin/console images:rename
 */
class RenamePokemonImagesCommand extends Command
{
    private EntityManagerInterface $entity_manager;
    private Filesystem $file_system;
    private KernelInterface $kernel;

    public function __construct(EntityManagerInterface $entity_manager, Filesystem $file_system, KernelInterface $kernel)
    {
        parent::__construct(self::getDefaultName());
        $this->entity_manager = $entity_manager;
        $this->file_system = $file_system;
        $this->kernel = $kernel;
    }

    public static function getDefaultName(): string
    {
        return 'images:rename';
    }

    public function configure(): void
    {
        $this->setDescription('Rename all pokemon images to just be the series + nr');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Starting renaming process');

        $skipped = 0;
        $pokemon_path = $this->kernel->getProjectDir() . '/public/images/pokemon/';
        $all_pokemon = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        foreach ($all_pokemon as $pokemon)
        {
            $old_name = $pokemon->getUniqueIdentifier().'.png';
            $new_name = $pokemon->getUniqueIdentifierV2().'.png';

            $old_path = $pokemon_path . $old_name;
            $new_path = $pokemon_path . $new_name;

            if ($this->file_system->exists($old_path))
            {
                $this->file_system->rename($old_path, $new_path, true);
            }
            else
            {
                $skipped++;
                $io->warning("Image $old_name.png does not exist, skipping...");
            }
        }

        $total = count($all_pokemon);
        $io->success("Done renaming {($total-$skipped)}/$total");
        return 0;
    }
}