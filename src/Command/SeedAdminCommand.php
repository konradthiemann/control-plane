<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-admin',
    description: 'Creates or updates the single admin user from ADMIN_EMAIL / ADMIN_PASSWORD env vars',
)]
class SeedAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $_SERVER['ADMIN_EMAIL'] ?? null;
        $password = $_SERVER['ADMIN_PASSWORD'] ?? null;

        if (!$email || !$password) {
            $io->error('ADMIN_EMAIL and ADMIN_PASSWORD must be set (as env vars, not committed).');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]) ?? new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Admin user "%s" is ready.', $email));

        return Command::SUCCESS;
    }
}
