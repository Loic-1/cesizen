<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Repository\RefreshTokenRepository;
use App\Service\VerificationEmailSender;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends ApiTestCase
{
    public function testRegisterCreatesUserAndSendsVerificationEmail(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'new.user@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = $this->responseData();

        self::assertSame('Registration successful. Please verify your email address.', $data['message']);
        self::assertSame('new.user@example.com', $data['user']['email']);
        self::assertNull($this->responseCookie());
        self::assertNull($this->browserCookieValue());

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('new.user@example.com');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        $sentEmails = static::getContainer()->get(VerificationEmailSender::class)->sentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('new.user@example.com', $sentEmails[0]['to']);
        self::assertStringContainsString('/auth/verify-email?token=', $sentEmails[0]['verificationUrl']);
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

    public function testLoginRejectsUnverifiedUsers(): void
    {
        $this->createUser('pending@example.com', 'password123', ['ROLE_USER'], false);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('Please verify your email address before logging in.', $this->responseData()['message']);
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

    public function testRefreshTokenRejectsUsersWhoBecomeUnverified(): void
    {
        $user = $this->createUser('refresh@example.com', 'password123');

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);
        self::assertNotNull($this->browserCookieValue());

        $this->mergePatchRequest('PATCH', '/users/me', [
            'email' => 'pending@example.com',
        ], $this->authHeaderFor($user));
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/auth/refresh-token');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(
            'Please verify your email address before accessing this resource.',
            $this->responseData()['message']
        );
        self::assertSame('', $this->browserCookieValue() ?? '');
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

    public function testVerifyEmailMarksUserAsVerified(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'verify.me@example.com',
            'password' => 'password123',
        ]);

        $sentEmails = static::getContainer()->get(VerificationEmailSender::class)->sentEmails();
        self::assertCount(1, $sentEmails);

        $query = parse_url($sentEmails[0]['verificationUrl'], PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);

        $this->client->request(
            'GET',
            '/auth/verify-email',
            ['token' => $params['token'] ?? null],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        self::assertResponseIsSuccessful();
        self::assertSame('Email verified.', $this->responseData()['message']);

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('verify.me@example.com');
        self::assertNotNull($user);
        self::assertTrue($user->isVerified());
    }

    public function testResendVerificationEmailIssuesANewVerificationLink(): void
    {
        $this->createUser('pending@example.com', 'password123', ['ROLE_USER'], false);

        $this->jsonRequest('POST', '/auth/resend-verification-email', [
            'email' => 'pending@example.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            'If the account exists and is not yet verified, a verification email has been sent.',
            $this->responseData()['message']
        );

        $sentEmails = static::getContainer()->get(VerificationEmailSender::class)->sentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('pending@example.com', $sentEmails[0]['to']);
    }
}
