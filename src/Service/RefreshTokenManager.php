<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class RefreshTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly int $refreshTokenTtl,
    ) {
    }

    public function create(User $user): string
    {
        // trouvé sur stackoverflow 😎
        $plainToken = bin2hex(random_bytes(64));
        $refreshToken = (new RefreshToken())
            ->setUser($user)
            ->setTokenHash($this->hash($plainToken))
            ->setExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', $this->refreshTokenTtl)));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $plainToken;
    }

    public function rotate(string $plainToken): string
    {
        $refreshToken = $this->getValidToken($plainToken);
        $refreshToken->revoke();
        $newPlainToken = $this->create($refreshToken->getUser());
        $this->entityManager->flush();

        return $newPlainToken;
    }

    public function revoke(string $plainToken): void
    {
        $refreshToken = $this->getValidToken($plainToken);
        $refreshToken->revoke();
        $this->entityManager->flush();
    }

    public function revokeAllForUser(User $user): int
    {
        return $this->refreshTokenRepository->revokeActiveForUser($user, new \DateTimeImmutable());
    }

    public function getValidToken(string $plainToken): RefreshToken
    {
        $refreshToken = $this->refreshTokenRepository->findActiveByHash(
            $this->hash($plainToken),
            new \DateTimeImmutable()
        );

        if ($refreshToken === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid refresh token.');
        }

        return $refreshToken;
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
