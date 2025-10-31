<?php

namespace App\Command\TcgDex;

use App\Service\TcgDexApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupTcgDexCommand extends Command
{
    public function __construct(
        private readonly TcgDexApiService $tcgdex_api_service,
    )
    {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'setup:tcg-dex';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Setup TCG Dex");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Setting up sets');
        $this->tcgdex_api_service->getAllSets();

        $io->success('Done!');
        return 0;
    }
}