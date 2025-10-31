<?php

namespace App\Command;

use App\Entity\Pokedex;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class SetupPokedexCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entity_manager,
        private KernelInterface $kernel,
    ){
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'setup:pokedex';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Update pokemon and missing pokemon");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->entity_manager->getRepository(Pokedex::class)->clearPokedex();

        $base_dir = $this->kernel->getProjectDir();
        $path = $base_dir . '/pokedex.txt';
        $content = file_get_contents($path);
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            [$dexNr, $name] = explode('|', $line);

            $this->entity_manager->persist(new Pokedex((int)$dexNr, $name));
        }

        $this->entity_manager->flush();

        return 0;
    }
}