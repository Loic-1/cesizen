<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Service\VerificationEmailSender;
use Symfony\Component\HttpFoundation\Response;

class MeControllerTest extends ApiTestCase
{
    public function testShowReturnsCurrentUser(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');

        $this->client->request(
            'GET',
            '/users/me',
            [],
            [],
            array_merge([
                'HTTP_ACCEPT' => 'application/json',
            ], $this->authHeaderFor($user))
        );

        self::assertResponseIsSuccessful();
        self::assertSame('reader@example.com', $this->responseData()['email']);
    }

    public function testShowRequiresAuthentication(): void
    {
        $this->client->request('GET', '/users/me', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Authentication required.', $this->responseData()['message']);
    }

    public function testUpdateChangesCurrentUserEmail(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');

        $this->mergePatchRequest('PATCH', '/users/me', [
            'email' => 'updated@example.com',
        ], $this->authHeaderFor($user));

        self::assertResponseIsSuccessful();
        self::assertSame('updated@example.com', $this->responseData()['email']);

        $reloadedUser = static::getContainer()->get(UserRepository::class)->findOneByEmail('updated@example.com');
        self::assertNotNull($reloadedUser);
        self::assertFalse($reloadedUser->isVerified());

        $sentEmails = static::getContainer()->get(VerificationEmailSender::class)->sentEmails();
        self::assertCount(1, $sentEmails);
        self::assertSame('updated@example.com', $sentEmails[0]['to']);
    }

    public function testUpdateRejectsDuplicateEmail(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');
        $this->createUser('taken@example.com', 'password123');

        $this->mergePatchRequest('PATCH', '/users/me', [
            'email' => 'taken@example.com',
        ], $this->authHeaderFor($user));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Email already in use.', $this->responseData()['message']);
    }

    public function testChangePasswordUpdatesStoredPassword(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');

        $this->mergePatchRequest('PATCH', '/users/me/password', [
            'currentPassword' => 'password123',
            'newPassword' => 'new-password123',
        ], $this->authHeaderFor($user));

        self::assertResponseIsSuccessful();
        self::assertSame('Password updated.', $this->responseData()['message']);

        $repository = static::getContainer()->get(UserRepository::class);
        $reloadedUser = $repository->findOneByEmail('reader@example.com');

        self::assertNotNull($reloadedUser);
        self::assertTrue(
            static::getContainer()->get('security.user_password_hasher')->isPasswordValid(
                $reloadedUser,
                'new-password123'
            )
        );
    }

    public function testChangePasswordRejectsInvalidCurrentPassword(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');

        $this->mergePatchRequest('PATCH', '/users/me/password', [
            'currentPassword' => 'bad-password',
            'newPassword' => 'new-password123',
        ], $this->authHeaderFor($user));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('Current password is invalid.', $this->responseData()['message']);
    }

    public function testDeleteRemovesCurrentUser(): void
    {
        $user = $this->createUser('reader@example.com', 'password123');

        $this->client->request(
            'DELETE',
            '/users/me',
            [],
            [],
            array_merge([
                'HTTP_ACCEPT' => 'application/json',
            ], $this->authHeaderFor($user))
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull(static::getContainer()->get(UserRepository::class)->find($user->getId()));
    }
}
