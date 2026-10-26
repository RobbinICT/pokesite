<?php

namespace App\Command;

use App\Entity\Minifigure;
use App\Service\DatabaseManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console update:database:minifigures clean_import
 * @example php bin/console update:database:minifigures add
 * @example php bin/console update:database:minifigures fill-images
 */
class UpdateMinifiguresCommand extends Command
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
        return 'update:database:minifigures';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Import '/var/minifigures.csv' or fill in missing image URLs from BrickLink")
            ->addArgument('update_mode', InputArgument::REQUIRED, 'clean_import, add or fill-images')
            ->addArgument('delimiter', InputArgument::OPTIONAL, 'Choose the delimiter', ';');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $delimiter = $input->getArgument('delimiter');
        if ($delimiter !== ';' && $delimiter !== ',' && $delimiter !== "\t")
        {
            $io->error("2nd argument should be ';', ',' or a tab");
            return 1;
        }
        $update_mode = filter_var($input->getArgument('update_mode'));

        switch ($update_mode)
        {
            case 'clean_import':
                $io->note('Clean importing minifigures.csv');
                $this->entity_manager->getRepository(Minifigure::class)->clearMinifigures();
                // no break: fall through to import the CSV
            case 'add':
                $io->note('Adding minifigures.csv to existing data');
                $this->database_manager->importMinifiguresCsv($delimiter);
                break;
            case 'fill-images':
            case 'scrape-images': // old name
                $io->note('Filling in missing minifigure image URLs from BrickLink');
                $report = $this->database_manager->fillMinifigureImageUrls();
                $io->success(sprintf('Updated %d image(s)', \count($report['updated'])));
                if (!empty($report['failed']))
                {
                    $io->warning(sprintf('Failed for %d: %s', \count($report['failed']), implode(', ', $report['failed'])));
                }
                return 0;
            default:
                $io->error("Argument should be 'clean_import', 'add' or 'fill-images'");
                return 1;
        }

        $io->success('Updated database successfully');
        return 0;
    }
}
