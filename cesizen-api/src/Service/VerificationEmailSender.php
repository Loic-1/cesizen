<?php

namespace App\Service;

use App\Entity\User;

class VerificationEmailSender
{
    /**
     * @var list<array{to: string, subject: string, body: string, verificationUrl: string}>
     */
    private array $sentEmails = [];

    public function __construct(
        private readonly string $fromEmail,
        private readonly string $appEnv,
    ) {
    }

    public function send(User $user, string $verificationUrl): void
    {
        $subject = 'Vérification de votre courriel';
        $body = sprintf(
            "Bonjour,\n\nVeuillez vérifier votre adresse courriel en cliquant sur ce lien:\n%s\n\nCe lien expirera automatiquement.\n",
            $verificationUrl
        );

        $this->sentEmails[] = [
            'to' => $user->getEmail(),
            'subject' => $subject,
            'body' => $body,
            'verificationUrl' => $verificationUrl,
        ];

        if ($this->appEnv === 'test') {
            return;
        }

        $headers = [
            sprintf('From: %s', $this->fromEmail),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = mail($user->getEmail(), $subject, $body, implode("\r\n", $headers));
        if ($sent === false) {
            throw new \RuntimeException('Unable to send verification email.');
        }
    }

    /**
     * @return list<array{to: string, subject: string, body: string, verificationUrl: string}>
     */
    public function sentEmails(): array
    {
        return $this->sentEmails;
    }
}
