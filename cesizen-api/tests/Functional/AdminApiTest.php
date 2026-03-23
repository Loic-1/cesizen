<?php

namespace App\Tests\Functional;

use App\Repository\ArticleRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class AdminApiTest extends ApiTestCase
{
    public function testAdminUsersRoutesSupportListGetPatchAndDelete(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $user = $this->createUser('member@example.com', self::DEFAULT_PASSWORD);

        $this->client->request('GET', '/admin/users', [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->collectionItems());

        $this->client->request('GET', '/admin/users/'.$user->getId()->toRfc4122(), [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseIsSuccessful();
        self::assertSame('member@example.com', $this->responseData()['email']);

        $this->mergePatchRequest('PATCH', '/admin/users/'.$user->getId()->toRfc4122(), [
            'email' => 'updated-member@example.com',
            'roles' => ['ROLE_EDITOR'],
            'isVerified' => true,
        ], $this->authHeaderFor($admin));
        self::assertResponseIsSuccessful();
        $patchedData = $this->responseData();
        self::assertSame('updated-member@example.com', $patchedData['email']);
        $reloadedUser = static::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertNotNull($reloadedUser);
        self::assertContains('ROLE_EDITOR', $reloadedUser->getRoles());
        self::assertTrue($reloadedUser->isVerified());

        $this->client->request('DELETE', '/admin/users/'.$user->getId()->toRfc4122(), [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull(static::getContainer()->get(UserRepository::class)->find($user->getId()));
    }

    public function testArticlesRoutesSupportPublicReadAndAdminWrite(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', self::DEFAULT_PASSWORD);
        $article = $this->createArticle($author, 'Existing article', 'Existing content', 'Existing description');

        $this->client->request('GET', '/articles', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->collectionItems());

        $this->client->request('GET', '/articles/'.$article->getId()->toRfc4122(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('Existing article', $this->responseData()['title']);

        $this->jsonRequest('POST', '/admin/articles', [
            'userId' => $author->getId()->toRfc4122(),
            'title' => 'Created article',
            'description' => 'Created description',
            'content' => 'Created content',
        ], $this->authHeaderFor($admin));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createdData = $this->responseData();
        self::assertSame('Created article', $createdData['title']);

        $this->mergePatchRequest('PATCH', '/admin/articles/'.$article->getId()->toRfc4122(), [
            'title' => 'Updated article',
            'content' => 'Updated content',
        ], $this->authHeaderFor($admin));
        self::assertResponseIsSuccessful();
        self::assertSame('Updated article', $this->responseData()['title']);

        $this->client->request('DELETE', '/admin/articles/'.$article->getId()->toRfc4122(), [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull(static::getContainer()->get(ArticleRepository::class)->find($article->getId()));
    }

    public function testArticleCreationValidatesRequiredFields(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);

        $this->jsonRequest('POST', '/admin/articles', [
            'title' => 'Missing author',
            'content' => 'Some content',
        ], $this->authHeaderFor($admin));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testArticleCreationRejectsUnknownAuthor(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);

        $this->jsonRequest('POST', '/admin/articles', [
            'userId' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Missing author',
            'description' => 'Desc',
            'content' => 'Some content',
        ], $this->authHeaderFor($admin));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testFilesRoutesSupportPublicArticleListingAndAdminManagement(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);
        $author = $this->createUser('author@example.com', self::DEFAULT_PASSWORD);
        $article = $this->createArticle($author, 'File article', 'Content', 'Description');
        $file = $this->createFile($article);

        $this->client->request('GET', '/articles/'.$article->getId()->toRfc4122().'/files', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->collectionItems());

        $this->client->request('GET', '/admin/files', [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->collectionItems());

        $this->client->request('GET', '/admin/files/'.$file->getId()->toRfc4122(), [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseIsSuccessful();
        self::assertSame('cover.pdf', $this->responseData()['originalName']);

        $this->jsonRequest('POST', '/admin/articles/'.$article->getId()->toRfc4122().'/files', [
            'originalName' => 'sample.epub',
            'storagePath' => '/files/sample.epub',
            'mimeType' => 'application/epub+zip',
            'size' => 2048,
        ], $this->authHeaderFor($admin));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('sample.epub', $this->responseData()['originalName']);

        $this->client->request('DELETE', '/admin/files/'.$file->getId()->toRfc4122(), [], [], array_merge([
            'HTTP_ACCEPT' => 'application/json',
        ], $this->authHeaderFor($admin)));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull(static::getContainer()->get(FileRepository::class)->find($file->getId()));
    }

    public function testArticleFilesRouteReturnsNotFoundForUnknownArticle(): void
    {
        $this->client->request('GET', '/articles/00000000-0000-0000-0000-000000000000/files', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminFileCreationReturnsNotFoundForUnknownArticle(): void
    {
        $admin = $this->createUser('admin@example.com', self::DEFAULT_PASSWORD, ['ROLE_ADMIN']);

        $this->jsonRequest('POST', '/admin/articles/00000000-0000-0000-0000-000000000000/files', [
            'originalName' => 'sample.epub',
            'storagePath' => '/files/sample.epub',
            'mimeType' => 'application/epub+zip',
            'size' => 2048,
        ], $this->authHeaderFor($admin));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
