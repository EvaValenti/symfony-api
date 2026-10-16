<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use App\Service\EmailSender;

class MailerController extends AbstractController
{
    private MailerInterface $mailer;
    private EmailSender $emailSender;

    public function __construct(MailerInterface $mailer, EmailSender $emailSender) {
        $this->mailer = $mailer; // if you still need it
        $this->emailSender = $emailSender;
    }

    public function sendEmail(Request $request): Response
    {
        $to = $request->query->get('to') ?? $request->request->get('to');
        if (!$to) { return new Response('Le paramètre "to" est requis.', 400); }

        $this->emailSender->sendWeeklyNewsletter($to);
        return new Response('Email envoyé.', 200);
    }
}
