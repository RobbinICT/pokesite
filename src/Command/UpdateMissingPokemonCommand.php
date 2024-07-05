<?php

namespace App\Command;

use App\Service\DatabaseManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console update:missing:pokemon
 */
class UpdateMissingPokemonCommand extends Command
{
    private DatabaseManager $database_manager;

    public function __construct(DatabaseManager $database_manager)
    {
        parent::__construct(self::getDefaultName());
        $this->database_manager = $database_manager;
    }

    public static function getDefaultName(): string
    {
        return 'update:missing:pokemon';
    }

    public function configure(): void
    {
        $this->setDescription('Update missing pokemon by adding new ones and removing acquired ones');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Adding missing pokemon');
        $added = $this->database_manager->addMissingPokemon();
        if (!empty($added))
        {
            $count = \count($added);
            $io->success("Added $count missing pokemon");
        }

        $io->note('Removing acquired missing pokemon');
        $deleted = $this->database_manager->removeAcquiredMissingPokemon();
        if (!empty($deleted))
        {
            $count = \count($deleted);
            $io->success("Removed $count missing pokemon");
        }

        $io->success('Done updating missing pokemon');
        return 0;
    }
}