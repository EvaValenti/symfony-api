<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/email', name: 'send_email', methods: ['GET', 'POST'])]
    public function sendEmail(Request $request): Response
    {
        // Récupère le destinataire soit en query (?to=...), soit en body (form/post)
        $to = $request->query->get('to') ?? $request->request->get('to');

        if (!$to) {
            return new Response('Le paramètre "to" est requis.', Response::HTTP_BAD_REQUEST);
        }

        $email = (new TemplatedEmail())
            ->from('hello@demomailtrap.co')
            ->to($to) // Remplacez par votre email !
            ->subject('Les nouveaux jeux de la semaine !')
            ->htmlTemplate('templates/mail.html.twig');

        $this->mailer->send($email);

        return new Response('Email envoyé.', Response::HTTP_OK);
    }
}
