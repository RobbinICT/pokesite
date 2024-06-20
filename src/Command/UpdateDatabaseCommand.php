<?php

namespace App\Command;

use App\Entity\Pokemon;
use App\Service\DatabaseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console update:database:cards clean-import
 */
class UpdateDatabaseCommand extends Command
{
    private EntityManagerInterface $entity_manager;
    private DatabaseManager $database_manager;

    public function __construct(EntityManagerInterface $entity_manager, DatabaseManager $database_manager)
    {
        parent::__construct(self::getDefaultName());
        $this->entity_manager = $entity_manager;
        $this->database_manager = $database_manager;
    }

    public static function getDefaultName(): string
    {
        return 'update:database:cards';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Clean import the '/var/cards.csv' file or add to the existing data")
            ->addArgument('update_mode', InputArgument::REQUIRED, 'Clean import or add to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $update_mode = filter_var($input->getArgument('update_mode'));
        switch ($update_mode)
        {
            case 'clean-import':
                $io->note('Clean importing cards.csv');
                $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
            case 'add':
                $io->note('Adding cards.csv to existing data');
                $this->database_manager->importCsvFile();
                break;
            default:
                $io->error("Argument should be 'clean-import' or 'add'");
                return 1;
        }

        $io->success('Updated database successfully');
        return 0;
    }
}