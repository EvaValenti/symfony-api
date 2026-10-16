<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class MailerController extends AbstractController
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer){
        $this->mailer = $mailer;
    }

    #[Route('/email')]
    public static function sendEmail(string $to): void
    {
        $email = (new TemplatedEmail()
            ->from('hello@demomailtrap.co')
            ->to($to) // Remplacez par votre email !
            ->subject('Les nouveaux jeux de la semaine !')
            ->htmlTemplate('templates/mail.html.twig'));

        $this->mailer->send($email);

    }
}