<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/admin/users',
        paginationItemsPerPage: 20,
        security: "is_granted('ROLE_ADMIN')",
        normalizationContext: ['groups' => ['admin:user:read']]
    ),
    new Get(
        uriTemplate: '/admin/users/{id}',
        security: "is_granted('ROLE_ADMIN')",
        normalizationContext: ['groups' => ['admin:user:read']]
    ),
    new Patch(
        uriTemplate: '/admin/users/{id}',
        security: "is_granted('ROLE_ADMIN')",
        normalizationContext: ['groups' => ['admin:user:read']],
        denormalizationContext: ['groups' => ['admin:user:write']]
    ),
    new Delete(
        uriTemplate: '/admin/users/{id}',
        security: "is_granted('ROLE_ADMIN')"
    ),
])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['user:read', 'admin:user:read', 'article:read'])]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'admin:user:read', 'admin:user:write', 'article:read'])]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(type: 'json')]
    #[Assert\All([new Assert\Type('string')])]
    #[Groups(['user:read', 'admin:user:read', 'admin:user:write'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['user:read', 'admin:user:read', 'admin:user:write'])]
    private bool $isVerified = false;

    #[ORM\Column]
    #[Groups(['user:read', 'admin:user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read', 'admin:user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Article::class, orphanRemoval: true)]
    private Collection $articles;

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class, orphanRemoval: true)]
    private Collection $refreshTokens;

    /**
     * @var Collection<int, EmailVerificationToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EmailVerificationToken::class, orphanRemoval: true)]
    private Collection $emailVerificationTokens;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->articles = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
        $this->emailVerificationTokens = new ArrayCollection();
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $normalized = array_map(static fn (mixed $role): string => (string) $role, $roles);
        $normalized[] = 'ROLE_USER';
        $this->roles = array_values(array_unique($normalized));

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

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
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    /**
     * @return Collection<int, RefreshToken>
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    /**
     * @return Collection<int, EmailVerificationToken>
     */
    public function getEmailVerificationTokens(): Collection
    {
        return $this->emailVerificationTokens;
    }

    public function eraseCredentials(): void
    {
    }
}
