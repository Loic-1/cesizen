<?php

namespace App\Tests\Unit;

use App\Service\RefreshTokenCookieManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookieManagerTest extends TestCase
{
    public function testCreateCookieUsesConfiguredAttributes(): void
    {
        $manager = new RefreshTokenCookieManager('refresh_token', '/auth/refresh-token', Cookie::SAMESITE_STRICT, true);
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $cookie = $manager->createCookie('plain-refresh-token', $expiresAt);

        self::assertSame('refresh_token', $cookie->getName());
        self::assertSame('plain-refresh-token', $cookie->getValue());
        self::assertSame('/auth/refresh-token', $cookie->getPath());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame($expiresAt->getTimestamp(), $cookie->getExpiresTime());
    }

    public function testClearCookieKeepsCookieContractAndExpiresInThePast(): void
    {
        $manager = new RefreshTokenCookieManager('refresh_token', '/auth/refresh-token', Cookie::SAMESITE_STRICT, true);

        $cookie = $manager->clearCookie();

        self::assertSame('refresh_token', $cookie->getName());
        self::assertSame('', $cookie->getValue());
        self::assertSame('/auth/refresh-token', $cookie->getPath());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertLessThan(time(), $cookie->getExpiresTime());
    }
}
