<?php

namespace App\Command;

use App\Entity\Config;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ChangeConfigVariableCommand extends Command
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        parent::__construct($this::getDefaultName());
        $this->entity_manager = $entity_manager;
    }

    public static function getDefaultName(): string
    {
        return 'config:variable';
    }

    public function configure(): void
    {
        $this
            ->setDescription('Change config value')
            ->addArgument('variable', InputArgument::REQUIRED, 'The variable you want to change')
            ->addArgument('value', InputArgument::REQUIRED, 'The value you want to change to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $variable = filter_var($input->getArgument('variable'));
        $value = filter_var($input->getArgument('value'), FILTER_VALIDATE_BOOLEAN);

        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        if ($config === null)
        {
            $io->note('No config found, making new one');
            $config = new Config();
            $this->entity_manager->persist($config);
        }

        switch ($variable)
        {
            case 'super-action':
            case 'sa':
                $io->note("Changing super action to $value");
                $config->setIsSuperActionsEnabled($value);
                break;
            case 'paradox-rift':
            case 'pr':
                $io->note("Changing exclude paradox rift to $value");
                $config->setIsParadoxRiftExclude($value);
                break;
            case 'local-card':
            case 'lc':
                $io->note("Changing use local card to $value");
                $config->setUseLocalCards($value);
                break;
            case 'alphabetical-order':
            case 'ao':
                $io->note("Changing in alphabetical order to $value");
                $config->setIsInAlphabeticalOrder($value);
                break;
            default:
                $io->error("Invalid config variable: $variable");
                return 1;
        }

        $this->entity_manager->flush();
        $io->success('Successfully changed config value');
        return 0;
    }
}