<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenCookieManager
{
    public function __construct(
        private readonly string $cookieName,
        private readonly string $cookiePath,
        private readonly string $sameSite,
        private readonly bool $secure,
    ) {
    }

    public function createCookie(string $refreshToken, \DateTimeImmutable $expiresAt): Cookie
    {
        return Cookie::create(
            $this->cookieName,
            $refreshToken,
            $expiresAt,
            $this->cookiePath,
            null,
            $this->secure,
            true,
            false,
            $this->sameSite
        );
    }

    public function clearCookie(): Cookie
    {
        return Cookie::create(
            $this->cookieName,
            '',
            new \DateTimeImmutable('-1 day'),
            $this->cookiePath,
            null,
            $this->secure,
            true,
            false,
            $this->sameSite
        );
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
