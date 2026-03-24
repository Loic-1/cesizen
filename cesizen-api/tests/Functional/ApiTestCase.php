<?php

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\File;
use App\Entity\User;
use App\Security\JwtTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected const DEFAULT_PASSWORD = 'MotDePasseFort123!';

    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get('cache.app')->clear();

        $this->resetDatabase();
    }

    protected function jsonRequest(string $method, string $uri, array $payload = [], array $server = []): void
    {
        $content = $payload === [] ? '{}' : json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $server);

        $this->client->request($method, $uri, [], [], $headers, $content);
    }

    protected function mergePatchRequest(string $method, string $uri, array $payload, array $server = []): void
    {
        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = array_merge([
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], $server);

        $this->client->request($method, $uri, [], [], $headers, $content);
    }

    protected function createUser(
        string $email = 'user@example.com',
        string $password = self::DEFAULT_PASSWORD,
        array $roles = ['ROLE_USER'],
        bool $isVerified = true,
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setIsVerified($isVerified);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createArticle(
        ?User $user,
        string $title = 'First article',
        string $content = 'Article body',
        ?string $description = 'Short description'
    ): Article {
        $article = (new Article())
            ->setTitle($title)
            ->setDescription($description)
            ->setContent($content);

        $article->setUser($user);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    protected function createFile(
        Article $article,
        string $originalName = 'cover.pdf',
        string $storagePath = '/files/cover.pdf',
        string $mimeType = 'application/pdf',
        int $size = 512
    ): File {
        $file = (new File())
            ->setArticle($article)
            ->setOriginalName($originalName)
            ->setStoragePath($storagePath)
            ->setMimeType($mimeType)
            ->setSize($size);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }

    protected function authHeaderFor(User $user): array
    {
        $tokenManager = static::getContainer()->get(JwtTokenManager::class);

        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$tokenManager->create($user),
        ];
    }

    protected function responseCookie(string $name = 'refresh_token'): ?\Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    protected function browserCookieValue(string $name = 'refresh_token', string $path = '/auth/refresh-token'): ?string
    {
        return $this->client->getCookieJar()->get($name, $path)?->getValue();
    }

    protected function setBrowserCookie(string $name, string $value, string $path = '/auth/refresh-token'): void
    {
        $this->client->getCookieJar()->set(new Cookie($name, $value, null, $path));
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectionItems(): array
    {
        $data = $this->responseData();

        if (isset($data['member']) && is_array($data['member'])) {
            return $data['member'];
        }

        if (isset($data['hydra:member']) && is_array($data['hydra:member'])) {
            return $data['hydra:member'];
        }

        return array_is_list($data) ? $data : [];
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        if ($metadata !== []) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        $this->entityManager->clear();
    }
}
