<?php
namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailSender
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendWeeklyNewsletter(string $to): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('hello@demomailtrap.co', 'Demo'))
            ->to($to)
            ->subject('Les nouveaux jeux de la semaine !')
            ->htmlTemplate('mail.html.twig'); // prefer 'mail.html.twig' inside templates/

        $this->mailer->send($email);
    }
}