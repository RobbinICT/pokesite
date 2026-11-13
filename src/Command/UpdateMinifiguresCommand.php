<?php

namespace App\Command;

use App\Admin\Type\MinifigureType;
use App\Entity\Minifigure;
use App\Service\CollectableImageStorage;
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
 * @example php bin/console update:database:minifigures download-images
 */
class UpdateMinifiguresCommand extends Command
{
    use ReportsProgress;

    private EntityManagerInterface $entity_manager;
    private DatabaseManager $database_manager;

    private CollectableImageStorage $image_storage;
    private MinifigureType $minifigure_type;

    public function __construct(EntityManagerInterface $entity_manager, DatabaseManager $database_manager, CollectableImageStorage $image_storage, MinifigureType $minifigure_type)
    {
        parent::__construct(self::getDefaultName());
        $this->entity_manager = $entity_manager;
        $this->database_manager = $database_manager;
        $this->image_storage = $image_storage;
        $this->minifigure_type = $minifigure_type;
    }

    public static function getDefaultName(): string
    {
        return 'update:database:minifigures';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Import '/var/minifigures.csv', fill in missing image URLs from BrickLink or download missing images")
            ->addArgument('update_mode', InputArgument::REQUIRED, 'clean_import, add, fill-images or download-images')
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
                $report = $this->database_manager->importMinifiguresCsv($delimiter);
                $io->success(sprintf('Imported %d minifigure(s)', $report['imported']));
                if (!empty($report['skipped']))
                {
                    $io->note(sprintf('Skipped %d already in the database: %s', \count($report['skipped']), implode(', ', $report['skipped'])));
                }
                return 0;
            case 'download-images':
                $io->note('Downloading minifigure images that are not on the server yet');
                $downloaded = 0;
                $failed = [];
                $progress = $this->progressReporter($output);
                $minifigures = $this->entity_manager->getRepository(Minifigure::class)->findAll();
                foreach ($minifigures as $i => $minifigure)
                {
                    $progress($i + 1, \count($minifigures), 'Downloading images');
                    $result = $this->image_storage->downloadMissing($this->minifigure_type, $minifigure);
                    if ($result === true)
                    {
                        $downloaded++;
                        $io->writeln('Downloaded '.$minifigure->getIdentifier());
                    }
                    elseif (\is_string($result))
                    {
                        $failed[] = $minifigure->getIdentifier();
                        $io->writeln(sprintf('Failed %s: %s', $minifigure->getIdentifier(), $result));
                    }
                }
                $io->success(sprintf('Downloaded %d image(s)', $downloaded));
                if (!empty($failed))
                {
                    $io->warning(sprintf('Failed for %d: %s', \count($failed), implode(', ', $failed)));
                }
                return 0;
            case 'fill-images':
            case 'scrape-images': // old name
                $io->note('Filling in missing minifigure image URLs from BrickLink');
                $report = $this->database_manager->fillMinifigureImageUrls($this->progressReporter($output));
                $io->success(sprintf('Updated %d image(s)', \count($report['updated'])));
                if (!empty($report['failed']))
                {
                    $io->warning(sprintf('Failed for %d: %s', \count($report['failed']), implode(', ', $report['failed'])));
                }
                return 0;
            default:
                $io->error("Argument should be 'clean_import', 'add', 'fill-images' or 'download-images'");
                return 1;
        }

        $io->success('Updated database successfully');
        return 0;
    }
}
