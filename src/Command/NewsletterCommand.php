<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Controller\MailerController;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

#[AsCommand(
    name: 'app:newsletter',
    description: 'Permet d\'envoyer un mail aux utilisateurs ayant souscrit à la newsletter annonçant la sortie des jeux dans les 7 prochains jours.',
)]
class NewsletterCommand{
    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        EntityManagerInterface $entityManager,
        #[Argument('Argument description')] string $arg = '',
        #[Option('Option description')] bool $enable = false,
    ): int {
        $subscribers = $entityManager->getRepository(User::class)->findBySubscription();

        $io->writeln('Envoi de l\'email en cours...');

        foreach($subscribers as $subscriber){
            MailerController::sendEmail($subscriber->getEmail());
        }

        $io->success('Email envoyé avec succès.');

        return Command::SUCCESS;
    }
}
