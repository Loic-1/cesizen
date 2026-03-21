<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\JwtTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtTokenManagerTest extends TestCase
{
    public function testCreateAndParseRoundTrip(): void
    {
        $user = (new User())
            ->setEmail('member@example.com')
            ->setRoles(['ROLE_ADMIN']);
        $manager = new JwtTokenManager('test-secret', 3600);

        $token = $manager->create($user);
        $payload = $manager->parse($token);

        self::assertSame($user->getId()->toRfc4122(), $payload['sub']);
        self::assertSame('member@example.com', $payload['email']);
        self::assertContains('ROLE_ADMIN', $payload['roles']);
        self::assertContains('ROLE_USER', $payload['roles']);
        self::assertGreaterThan($payload['iat'], $payload['exp']);
    }

    public function testParseRejectsMalformedTokens(): void
    {
        $manager = new JwtTokenManager('test-secret', 3600);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Invalid access token.');

        $manager->parse('not-a-jwt');
    }

    public function testParseRejectsTokensWithInvalidSignature(): void
    {
        $user = (new User())
            ->setEmail('member@example.com')
            ->setRoles(['ROLE_USER']);
        $manager = new JwtTokenManager('test-secret', 3600);

        $parts = explode('.', $manager->create($user));
        $parts[2] = 'tampered-signature';

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Invalid access token signature.');

        $manager->parse(implode('.', $parts));
    }
}
