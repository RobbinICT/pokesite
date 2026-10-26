<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates an admin user, or resets the password when the username already exists.
 *
 * @example php bin/console app:user:create robbin
 */
class CreateUserCommand extends Command
{
    private EntityManagerInterface $entity_manager;
    private UserPasswordHasherInterface $password_hasher;

    public function __construct(EntityManagerInterface $entity_manager, UserPasswordHasherInterface $password_hasher)
    {
        parent::__construct(self::getDefaultName());
        $this->entity_manager = $entity_manager;
        $this->password_hasher = $password_hasher;
    }

    public static function getDefaultName(): string
    {
        return 'app:user:create';
    }

    public function configure(): void
    {
        $this
            ->setDescription('Create an admin user (or reset its password)')
            ->addArgument('username', InputArgument::REQUIRED, 'Login name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = trim($input->getArgument('username'));

        $password = $io->askHidden('Password', function (?string $value) {
            if ($value === null || \strlen($value) < 8)
            {
                throw new \RuntimeException('Password must be at least 8 characters.');
            }
            return $value;
        });
        $repeat = $io->askHidden('Repeat password');
        if ($password !== $repeat)
        {
            $io->error('Passwords do not match');
            return 1;
        }

        $user = $this->entity_manager->getRepository(User::class)->findOneBy(['username' => $username]);
        $is_new = $user === null;
        if ($is_new)
        {
            $user = new User($username);
            $this->entity_manager->persist($user);
        }
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->password_hasher->hashPassword($user, $password));
        $this->entity_manager->flush();

        $io->success($is_new ? "Created admin user '$username'" : "Updated password for '$username'");
        return 0;
    }
}
