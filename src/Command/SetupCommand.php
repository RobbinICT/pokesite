<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupCommand extends Command
{

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    public static function getDefaultName(): string
    {
        return 'setup:database';
    }

    public function configure(): void
    {
        $this
            ->setDescription("Update pokemon and missing pokemon");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();

        // Define commands and their arguments
        $commands = [
            [
                'name' => UpdateDatabaseCommand::getDefaultName(),
                'arguments' => [
                    'update_mode' => 'clean_import',
                ],
            ],
            [
                'name' => UpdateMissingPokemonCommand::getDefaultName(),
                'arguments' => [],
            ],
        ];

        foreach ($commands as $commandInfo) {
            $command = $application->find($commandInfo['name']);
            $arguments = array_merge(
                ['command' => $commandInfo['name']],
                $commandInfo['arguments']
            );

            $commandInput = new ArrayInput($arguments);
            $returnCode = $command->run($commandInput, $output);

            if ($returnCode !== Command::SUCCESS) {
                $io->error(sprintf('Command "%s" failed with exit code %d.', $commandInfo['name'], $returnCode));
                return $returnCode;
            }
        }

        $io->success('All commands executed successfully.');
        return Command::SUCCESS;
    }

}