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
            'password' => self::DEFAULT_PASSWORD,
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
        self::assertStringContainsString('/verify-email?token=', $sentEmails[0]['verificationUrl']);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->createUser('existing@example.com');

        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'existing@example.com',
            'password' => self::DEFAULT_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Email already in use.', $this->responseData()['message']);
    }

    public function testRegisterRejectsPasswordsShorterThanTwelveCharacters(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'too-short@example.com',
            'password' => 'court123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('password:', $this->responseData()['detail']);
    }

    public function testRegisterRejectsCommonPasswords(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'common@example.com',
            'password' => 'password123!',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('Choose a less common password.', $this->responseData()['detail']);
    }

    public function testRegisterRejectsPasswordsWithoutExpectedCharacterClasses(): void
    {
        $this->jsonRequest('POST', '/auth/register', [
            'email' => 'classes@example.com',
            'password' => 'motdepassefort',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString(
            'Password must contain at least one lowercase letter, one uppercase letter, and one special character.',
            $this->responseData()['detail']
        );
    }

    public function testLoginReturnsTokensForValidCredentials(): void
    {
        $this->createUser('login@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'login@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $this->createUser('pending@example.com', self::DEFAULT_PASSWORD, ['ROLE_USER'], false);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'pending@example.com',
            'password' => self::DEFAULT_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('Please verify your email address before logging in.', $this->responseData()['message']);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->createUser('login@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Invalid credentials.', $this->responseData()['message']);
    }

    public function testLoginIsRateLimitedAfterTooManyFailedAttempts(): void
    {
        $this->createUser('login@example.com', self::DEFAULT_PASSWORD);

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $this->jsonRequest('POST', '/auth/login', [
                'email' => 'login@example.com',
                'password' => 'wrong-password',
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('Too many login attempts. Please try again later.', $this->responseData()['message']);
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
    }

    public function testRefreshTokenUsesCookieAndRotatesToken(): void
    {
        $this->createUser('refresh@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'refresh@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $user = $this->createUser('refresh@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'refresh@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $this->createUser('rotate@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'rotate@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $user = $this->createUser('logout@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'logout@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $user = $this->createUser('member@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'member@example.com',
            'password' => self::DEFAULT_PASSWORD,
        ]);
        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'member@example.com',
            'password' => self::DEFAULT_PASSWORD,
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
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);

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
            'password' => self::DEFAULT_PASSWORD,
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

    public function testVerifyEmailRequiresToken(): void
    {
        $this->client->request('GET', '/auth/verify-email', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Missing verification token.', $this->responseData()['message']);
    }

    public function testVerifyEmailRejectsInvalidToken(): void
    {
        $this->client->request(
            'GET',
            '/auth/verify-email',
            ['token' => 'invalid-token'],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Invalid or expired verification token.', $this->responseData()['message']);
    }

    public function testResendVerificationEmailIssuesANewVerificationLink(): void
    {
        $this->createUser('pending@example.com', self::DEFAULT_PASSWORD, ['ROLE_USER'], false);

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

    public function testResendVerificationEmailDoesNotSendAnythingForVerifiedUsers(): void
    {
        $this->createUser('verified@example.com', self::DEFAULT_PASSWORD, ['ROLE_USER'], true);

        $this->jsonRequest('POST', '/auth/resend-verification-email', [
            'email' => 'verified@example.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            'If the account exists and is not yet verified, a verification email has been sent.',
            $this->responseData()['message']
        );

        $sentEmails = static::getContainer()->get(VerificationEmailSender::class)->sentEmails();
        self::assertCount(0, $sentEmails);
    }
}
