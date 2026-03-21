<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Article\ArticleInput;
use App\Repository\ArticleRepository;
use App\State\ArticleInputProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
#[ORM\Index(columns: ['created_at'], name: 'idx_article_created_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_article_user_id')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/articles',
            paginationItemsPerPage: 10,
            normalizationContext: ['groups' => ['article:read']]
        ),
        new Get(
            uriTemplate: '/articles/{id}',
            normalizationContext: ['groups' => ['article:read']]
        ),
        new Post(
            uriTemplate: '/admin/articles',
            security: "is_granted('ROLE_ADMIN')",
            input: ArticleInput::class,
            processor: ArticleInputProcessor::class,
            normalizationContext: ['groups' => ['article:read']]
        ),
        new Patch(
            uriTemplate: '/admin/articles/{id}',
            security: "is_granted('ROLE_ADMIN')",
            input: ArticleInput::class,
            processor: ArticleInputProcessor::class,
            normalizationContext: ['groups' => ['article:read']]
        ),
        new Delete(
            uriTemplate: '/admin/articles/{id}',
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    order: ['createdAt' => 'DESC']
)]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['article:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article:read'])]
    private User $user;

    #[ORM\Column(length: 255)]
    #[Groups(['article:read'])]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['article:read'])]
    private string $content;

    #[ORM\Column]
    #[Groups(['article:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, File>
     */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: File::class, orphanRemoval: true)]
    private Collection $files;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->files = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $this->updatedAt ?? $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null ? trim($description) : null;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }
}
