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
use Symfony\Component\Console\Input\InputInterface;
use App\Service\EmailSender;

#[AsCommand(
    name: 'app:newsletter',
    description: 'Permet d\'envoyer un mail aux utilisateurs ayant souscrit à la newsletter annonçant la sortie des jeux dans les 7 prochains jours.',
)]
class NewsletterCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager, private EmailSender $emailSender)
    {
        parent::__construct();
    }

    // public function __invoke(
    //     SymfonyStyle $io,
    //     OutputInterface $output,
    //     EntityManagerInterface $entityManager,
    //     #[Argument('Argument description')] string $arg = '',
    //     #[Option('Option description')] bool $enable = false,
    // ): int {
    //     $subscribers = $entityManager->getRepository(User::class)->findBySubscription();

    //     $io->writeln('Envoi de l\'email en cours...');

    //     foreach($subscribers as $subscriber){
    //         MailerController::sendEmail($subscriber->getEmail());
    //     }

    //     $io->success('Email envoyé avec succès.');

    //     return Command::SUCCESS;
    // }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subscribers = $this->entityManager->getRepository(User::class)->findBySubscription();

        if (empty($subscribers)) {
            $io->warning('Aucun abonné trouvé pour la newsletter.');
            return Command::SUCCESS;
        }

        $io->writeln('Envoi des emails en cours...');

        foreach ($subscribers as $subscriber) {
            try {
                $this->emailSender->sendWeeklyNewsletter($subscriber->getEmail());
            } catch (\Throwable $e) {
                $io->error('Erreur lors de l\'envoi à ' . $subscriber->getEmail() . ' : ' . $e->getMessage());
            }
        }

        $io->success('Emails envoyés avec succès.');

        return Command::SUCCESS;
    }
}
