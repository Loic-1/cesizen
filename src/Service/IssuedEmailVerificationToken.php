<?php

namespace App\Service;

class IssuedEmailVerificationToken
{
    public function __construct(
        public readonly string $plainToken,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
