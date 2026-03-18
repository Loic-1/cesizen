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
        self::assertArrayHasKey('refreshToken', $data);
        self::assertSame('new.user@example.com', $data['user']['email']);
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
        self::assertArrayHasKey('refreshToken', $data);
        self::assertSame('login@example.com', $data['user']['email']);
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

    public function testRefreshTokenRotatesToken(): void
    {
        $user = $this->createUser('refresh@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);
        $loginData = $this->responseData();

        $this->jsonRequest('POST', '/auth/refresh-token', [
            'refreshToken' => $loginData['refreshToken'],
        ], $this->authHeaderFor($user));

        self::assertResponseIsSuccessful();
        $data = $this->responseData();

        self::assertNotSame($loginData['refreshToken'], $data['refreshToken']);
        self::assertArrayHasKey('accessToken', $data);
        self::assertSame('refresh@example.com', $data['user']['email']);
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $user = $this->createUser('logout@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'password123',
        ]);
        $loginData = $this->responseData();

        $this->jsonRequest('POST', '/auth/logout', [
            'refreshToken' => $loginData['refreshToken'],
        ], $this->authHeaderFor($user));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $repository = static::getContainer()->get(RefreshTokenRepository::class);
        self::assertNull($repository->findActiveByHash(
            hash('sha256', $loginData['refreshToken']),
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
