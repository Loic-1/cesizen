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
        $admin = $this->createUser('admin@example.com', 'password123', ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', 'password123');
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
        $user = $this->createUser('reader@example.com', 'password123');

        $this->mergePatchRequest('PATCH', '/users/me', [], $this->authHeaderFor($user));

        self::assertResponseIsSuccessful();
        self::assertSame('reader@example.com', $this->responseData()['email']);

        $reloadedUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertNotNull($reloadedUser);
        self::assertSame('reader@example.com', $reloadedUser->getEmail());
    }

    public function testAdminCanCreateZeroSizedFile(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', 'password123');
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
}
