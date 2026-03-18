<?php

namespace App\Tests\Functional;

use App\Repository\RefreshTokenRepository;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends ApiTestCase
{
    public function testRegisterCreatesUserAndTokens(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'new.user@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();

        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayNotHasKey('refreshToken', $data);
        self::assertSame('new.user@example.com', $data['user']['email']);
        self::assertNotNull($this->responseCookie());
        self::assertTrue($this->responseCookie()?->isHttpOnly() ?? false);
        self::assertSame('/auth/refresh-token', $this->responseCookie()?->getPath());
        self::assertNotNull($this->browserCookieValue());
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->createUser('existing@example.com');

        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Email already in use.', $this->responseData()['message']);
    }

    public function testLoginReturnsTokensForValidCredentials(): void
    {
        $this->createUser('login@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->responseData();

        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayNotHasKey('refreshToken', $data);
        self::assertSame('login@example.com', $data['user']['email']);
        self::assertNotNull($this->responseCookie());
        self::assertTrue($this->responseCookie()?->isHttpOnly() ?? false);
        self::assertSame('/auth/refresh-token', $this->responseCookie()?->getPath());
        self::assertNotNull($this->browserCookieValue());
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->createUser('login@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Invalid credentials.', $this->responseData()['message']);
    }

    public function testRefreshTokenUsesCookieAndRotatesToken(): void
    {
        $this->createUser('refresh@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);
        $firstRefreshToken = $this->browserCookieValue();
        self::assertNotNull($firstRefreshToken);

        $this->jsonRequest('POST', '/auth/refresh-token');

        self::assertResponseIsSuccessful();
        $data = $this->responseData();
        $rotatedRefreshToken = $this->browserCookieValue();

        self::assertNotSame($firstRefreshToken, $rotatedRefreshToken);
        self::assertArrayHasKey('accessToken', $data);
        self::assertArrayNotHasKey('refreshToken', $data);
        self::assertSame('refresh@example.com', $data['user']['email']);
    }

    public function testRefreshTokenRejectsMissingCookie(): void
    {
        $this->jsonRequest('POST', '/auth/refresh-token');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Missing refresh token cookie.', $this->responseData()['message']);
    }

    public function testRotatedRefreshTokenCannotBeReused(): void
    {
        $this->createUser('rotate@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'rotate@example.com',
            'password' => 'password123',
        ]);
        $firstRefreshToken = $this->browserCookieValue();
        self::assertNotNull($firstRefreshToken);

        $this->jsonRequest('POST', '/auth/refresh-token');
        self::assertResponseIsSuccessful();
        $secondRefreshToken = $this->browserCookieValue();
        self::assertNotNull($secondRefreshToken);
        self::assertNotSame($firstRefreshToken, $secondRefreshToken);

        $this->setBrowserCookie('refresh_token', $firstRefreshToken);
        $this->jsonRequest('POST', '/auth/refresh-token');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Invalid refresh token.', $this->responseData()['message']);
    }

    public function testLogoutRevokesUserRefreshTokensAndClearsCookie(): void
    {
        $user = $this->createUser('logout@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'password123',
        ]);
        $refreshToken = $this->browserCookieValue();
        self::assertNotNull($refreshToken);

        $this->jsonRequest('POST', '/auth/logout', [], $this->authHeaderFor($user));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->browserCookieValue() ?? '');

        $repository = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repository->findActiveByHash(
            hash('sha256', $refreshToken),
            new \DateTimeImmutable()
        ));
    }

    public function testAdminCanRevokeAllSessionsForAUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', ['ROLE_ADMIN']);
        $user = $this->createUser('member@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);
        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'member@example.com',
            'password' => 'password123',
        ]);

        $this->client->request(
            'POST',
            '/admin/users/'.$user->getId()->toRfc4122().'/logout',
            [],
            [],
            array_merge([
                'HTTP_ACCEPT' => 'application/json',
            ], $this->authHeaderFor($admin))
        );

        self::assertResponseIsSuccessful();
        $data = $this->responseData();

        self::assertSame('User sessions revoked.', $data['message']);
        self::assertGreaterThanOrEqual(2, $data['revokedSessions']);
    }

    public function testAdminLogoutReturnsNotFoundForUnknownUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', ['ROLE_ADMIN']);

        $this->client->request(
            'POST',
            '/admin/users/00000000-0000-0000-0000-000000000000/logout',
            [],
            [],
            array_merge([
                'HTTP_ACCEPT' => 'application/json',
            ], $this->authHeaderFor($admin))
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('User not found.', $this->responseData()['message']);
    }
}
