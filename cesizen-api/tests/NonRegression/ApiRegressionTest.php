<?php

namespace App\Tests\NonRegression;

use App\Repository\ArticleRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiRegressionTest extends ApiTestCase
{
    public function testAdminArticlePatchKeepsExistingDescriptionWhenOmitted(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', self::DEFAULT_PASSWORD);
        $article = $this->createArticle($author, 'Original title', 'Original content', 'Keep me');

        $this->mergePatchRequest('PATCH', '/admin/articles/'.$article->getId()->toRfc4122(), [
            'title' => 'Updated title',
            'content' => 'Updated content',
        ], $this->authHeaderFor($admin));

        self::assertResponseIsSuccessful();
        self::assertSame('Keep me', $this->responseData()['description']);

        $reloadedArticle = static::getContainer()->get(ArticleRepository::class)->find($article->getId());
        self::assertNotNull($reloadedArticle);
        self::assertSame('Keep me', $reloadedArticle->getDescription());
    }

    public function testUpdateMeWithEmptyPayloadKeepsCurrentEmail(): void
    {
        $user = $this->createUser('reader@example.com', self::DEFAULT_PASSWORD);

        $this->mergePatchRequest('PATCH', '/users/me', [], $this->authHeaderFor($user));

        self::assertResponseIsSuccessful();
        self::assertSame('reader@example.com', $this->responseData()['email']);

        $reloadedUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertNotNull($reloadedUser);
        self::assertSame('reader@example.com', $reloadedUser->getEmail());
    }

    public function testAdminCanCreateZeroSizedFile(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', self::DEFAULT_PASSWORD);
        $article = $this->createArticle($author, 'File article', 'Body', 'Description');

        $this->jsonRequest('POST', '/admin/articles/'.$article->getId()->toRfc4122().'/files', [
            'originalName' => 'empty.txt',
            'storagePath' => '/files/empty.txt',
            'mimeType' => 'text/plain',
            'size' => 0,
        ], $this->authHeaderFor($admin));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(0, $this->responseData()['size']);

        $files = static::getContainer()->get(FileRepository::class)->findByArticleId($article->getId());
        self::assertCount(1, $files);
        self::assertSame(0, $files[0]->getSize());
    }

    public function testLogoutRemainsAccessibleAfterUserBecomesUnverified(): void
    {
        $user = $this->createUser('reader@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/auth/login', [
            'email' => 'reader@example.com',
            'password' => self::DEFAULT_PASSWORD,
        ]);
        $refreshToken = $this->browserCookieValue();
        self::assertNotNull($refreshToken);

        $this->mergePatchRequest('PATCH', '/users/me', [
            'email' => 'updated@example.com',
        ], $this->authHeaderFor($user));
        self::assertResponseIsSuccessful();

        $reloadedUser = static::getContainer()->get(UserRepository::class)->findOneByEmail('updated@example.com');
        self::assertNotNull($reloadedUser);
        self::assertFalse($reloadedUser->isVerified());

        $this->jsonRequest('POST', '/auth/logout', [], $this->authHeaderFor($reloadedUser));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->browserCookieValue() ?? '');
    }

    public function testAdminArticleContentIsSanitizedBeforePersisting(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', self::DEFAULT_PASSWORD);

        $this->jsonRequest('POST', '/admin/articles', [
            'userId' => $author->getId()->toRfc4122(),
            'title' => 'Unsafe article',
            'description' => 'Desc',
            'content' => '<p>Safe</p><script>alert(1)</script><img src="/uploads/articles/test.webp" onerror="alert(1)"><a href="javascript:alert(1)" target="_blank">Click</a><strong>Keep</strong>',
        ], $this->authHeaderFor($admin));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $sanitizedContent = $this->responseData()['content'] ?? null;
        self::assertIsString($sanitizedContent);
        self::assertStringNotContainsString('<script', $sanitizedContent);
        self::assertStringNotContainsString('onerror', $sanitizedContent);
        self::assertStringNotContainsString('javascript:', $sanitizedContent);
        self::assertStringContainsString('<p>Safe</p>', $sanitizedContent);
        self::assertStringContainsString('<img src="/uploads/articles/test.webp">', $sanitizedContent);
        self::assertStringContainsString('<a target="_blank" rel="noopener noreferrer">Click</a>', $sanitizedContent);
        self::assertStringContainsString('<strong>Keep</strong>', $sanitizedContent);

        $createdArticleId = $this->responseData()['id'] ?? null;
        self::assertIsString($createdArticleId);
        $reloadedArticle = static::getContainer()->get(ArticleRepository::class)->find($createdArticleId);
        self::assertNotNull($reloadedArticle);
        self::assertSame($sanitizedContent, $reloadedArticle->getContent());
    }
}
