<?php

namespace App\Tests\Unit;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Service\EmailVerificationManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmailVerificationManagerTest extends TestCase
{
    public function testCreateConsumesPreviousTokensAndPersistsANewOne(): void
    {
        $repository = $this->createMock(EmailVerificationTokenRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new EmailVerificationManager($repository, $entityManager, 3600);
        $user = (new User())->setEmail('member@example.com');

        $repository
            ->expects(self::once())
            ->method('consumeActiveForUser')
            ->with(self::identicalTo($user), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(1);

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (mixed $token) use ($user): bool {
                return $token instanceof EmailVerificationToken
                    && $token->getUser() === $user
                    && strlen($token->getTokenHash()) === 64;
            }));

        $entityManager->expects(self::once())->method('flush');

        $issuedToken = $manager->create($user);

        self::assertSame(64, strlen($issuedToken->plainToken));
        self::assertGreaterThan(new \DateTimeImmutable('+59 minutes'), $issuedToken->expiresAt);
    }

    public function testVerifyMarksUserAsVerifiedAndConsumesToken(): void
    {
        $repository = $this->createMock(EmailVerificationTokenRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new EmailVerificationManager($repository, $entityManager, 3600);

        $user = (new User())
            ->setEmail('member@example.com')
            ->setIsVerified(false);
        $token = (new EmailVerificationToken())
            ->setUser($user)
            ->setTokenHash(hash('sha256', 'plain-token'))
            ->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $repository
            ->expects(self::once())
            ->method('findActiveByHash')
            ->with(hash('sha256', 'plain-token'), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($token);

        $repository
            ->expects(self::once())
            ->method('consumeActiveForUser')
            ->with(self::identicalTo($user), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(0);

        $entityManager->expects(self::once())->method('flush');

        $verifiedUser = $manager->verify('plain-token');

        self::assertSame($user, $verifiedUser);
        self::assertTrue($verifiedUser->isVerified());
        self::assertNotNull($token->getConsumedAt());
    }

    public function testVerifyRejectsUnknownToken(): void
    {
        $repository = $this->createMock(EmailVerificationTokenRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $manager = new EmailVerificationManager($repository, $entityManager, 3600);

        $repository
            ->expects(self::once())
            ->method('findActiveByHash')
            ->willReturn(null);

        $entityManager->expects(self::never())->method('flush');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid or expired verification token.');

        $manager->verify('missing-token');
    }
}
