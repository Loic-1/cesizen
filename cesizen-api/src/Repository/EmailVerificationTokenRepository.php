<?php

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationToken>
 */
class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $now): ?EmailVerificationToken
    {
        return $this->createQueryBuilder('evt')
            ->andWhere('evt.tokenHash = :tokenHash')
            ->andWhere('evt.consumedAt IS NULL')
            ->andWhere('evt.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function consumeActiveForUser(User $user, \DateTimeImmutable $now): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->update(EmailVerificationToken::class, 'evt')
            ->set('evt.consumedAt', ':now')
            ->where('IDENTITY(evt.user) = :userId')
            ->andWhere('evt.consumedAt IS NULL')
            ->andWhere('evt.expiresAt > :now')
            ->setParameter('now', $now)
            ->setParameter('userId', $user->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
