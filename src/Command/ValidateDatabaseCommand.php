<?php

namespace App\Command;

use App\Entity\Pokemon;
use App\Service\ValidateManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console validate:database:cards
 */
class ValidateDatabaseCommand extends Command
{
    private EntityManagerInterface $entity_manager;
    private ValidateManager $validate_manager;

    public function __construct(EntityManagerInterface $entity_manager, ValidateManager $validate_manager)
    {
        parent::__construct(self::getDefaultName());
        $this->entity_manager = $entity_manager;
        $this->validate_manager = $validate_manager;
    }

    public static function getDefaultName(): string
    {
        return 'validate:database:cards';
    }

    public function configure(): void
    {
        $this->setDescription('Validate various database criteria');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Validating URLs');

        $all = $this->entity_manager->getRepository(Pokemon::class)->findAll();
        $wrong = $this->validate_manager->validateUrl($all);
        if (!empty($wrong))
        {
            $io->error('Found invalid URLs');
            $io->error($wrong);
            return 1;
        }

        $io->note('Validating generations');
        $wrong = $this->validate_manager->validateGeneration($all);
        if (!empty($wrong))
        {
            $io->error('Found invalid generations');
            $io->error($wrong);
            return 1;
        }

        $io->success('Database valid');
        return 0;
    }
}