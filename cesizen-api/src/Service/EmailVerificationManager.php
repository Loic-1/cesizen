<?php

namespace App\Service;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmailVerificationManager
{
    public function __construct(
        private readonly EmailVerificationTokenRepository $emailVerificationTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $emailVerificationTtl,
    ) {
    }

    public function create(User $user): IssuedEmailVerificationToken
    {
        $now = new \DateTimeImmutable();
        $this->emailVerificationTokenRepository->consumeActiveForUser($user, $now);

        $plainToken = bin2hex(random_bytes(32));
        $verificationToken = (new EmailVerificationToken())
            ->setUser($user)
            ->setTokenHash(hash('sha256', $plainToken))
            ->setExpiresAt($now->modify(sprintf('+%d seconds', $this->emailVerificationTtl)));

        $this->entityManager->persist($verificationToken);
        $this->entityManager->flush();

        return new IssuedEmailVerificationToken($plainToken, $verificationToken->getExpiresAt());
    }

    public function verify(string $plainToken): User
    {
        $token = $this->emailVerificationTokenRepository->findActiveByHash(
            hash('sha256', $plainToken),
            new \DateTimeImmutable()
        );

        if ($token === null) {
            throw new BadRequestHttpException('Invalid or expired verification token.');
        }

        $user = $token->getUser();
        $user->setIsVerified(true);
        $token->consume();
        $this->emailVerificationTokenRepository->consumeActiveForUser($user, new \DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }
}
