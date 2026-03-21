<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class JwtTokenManager
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly int $jwtTtl,
    ) {
    }

    public function create(User $user): string
    {
        $issuedAt = time();
        $payload = [
            'sub' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->jwtTtl,
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->jwtSecret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid access token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid access token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload) || !isset($payload['sub'], $payload['exp'])) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid access token payload.');
        }

        if ((int) $payload['exp'] <= time()) {
            throw new UnauthorizedHttpException('Bearer', 'Access token expired.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);
        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new UnauthorizedHttpException('Bearer', 'Malformed access token.');
        }

        return $decoded;
    }
}
