<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $now): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.tokenHash = :tokenHash')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function revokeActiveForUser(User $user, \DateTimeImmutable $now): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->update(RefreshToken::class, 'rt')
            ->set('rt.revokedAt', ':now')
            ->where('IDENTITY(rt.user) = :userId')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('now', $now)
            ->setParameter('userId', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
