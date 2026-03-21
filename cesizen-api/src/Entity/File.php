<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\File\FileInput;
use App\Repository\FileRepository;
use App\State\ArticleFilesProvider;
use App\State\FileInputProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'file')]
#[ORM\Index(columns: ['article_id'], name: 'idx_file_article_id')]
#[ORM\Index(columns: ['created_at'], name: 'idx_file_created_at')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/articles/{articleId}/files',
            uriVariables: [
                'articleId' => new Link(fromClass: Article::class, identifiers: ['id']),
            ],
            provider: ArticleFilesProvider::class,
            normalizationContext: ['groups' => ['file:read']],
            paginationEnabled: false
        ),
        new GetCollection(
            uriTemplate: '/admin/files',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => ['file:read']],
            paginationItemsPerPage: 20
        ),
        new Get(
            uriTemplate: '/admin/files/{id}',
            security: "is_granted('ROLE_ADMIN')",
            normalizationContext: ['groups' => ['file:read']]
        ),
        new Post(
            uriTemplate: '/admin/articles/{articleId}/files',
            security: "is_granted('ROLE_ADMIN')",
            read: false,
            uriVariables: [
                'articleId' => new Link(fromClass: Article::class, identifiers: ['id']),
            ],
            input: FileInput::class,
            processor: FileInputProcessor::class,
            normalizationContext: ['groups' => ['file:read']]
        ),
        new Delete(
            uriTemplate: '/admin/files/{id}',
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    order: ['createdAt' => 'DESC']
)]
class File
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['file:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['file:read'])]
    private Article $article;

    #[ORM\Column(length: 255)]
    #[Groups(['file:read'])]
    private string $originalName;

    #[ORM\Column(length: 255)]
    #[Groups(['file:read'])]
    private string $storagePath;

    #[ORM\Column(length: 100)]
    #[Groups(['file:read'])]
    private string $mimeType;

    #[ORM\Column]
    #[Groups(['file:read'])]
    private int $size;

    #[ORM\Column]
    #[Groups(['file:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function setArticle(Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = trim($originalName);

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = trim($storagePath);

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = trim($mimeType);

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
