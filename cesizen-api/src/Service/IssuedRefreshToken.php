<?php

namespace App\Service;

final class IssuedRefreshToken
{
    public function __construct(
        public readonly string $plainToken,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
