<?php

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

class SecurityAccessTest extends ApiTestCase
{
    public function testAdminRoutesRequireAuthentication(): void
    {
        $this->client->request('GET', '/admin/users', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('Authentication required.', $this->responseData()['message']);
    }

    public function testAdminRoutesRejectNonAdminUsers(): void
    {
        $user = $this->createUser('member@example.com', 'password123');

        $this->client->request('GET', '/admin/users', [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($user)));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
