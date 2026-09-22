<?php

namespace App\Entity;

use App\Repository\WebhookEndpointRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookEndpointRepository::class)]
#[ORM\Table(name: 'webhook_endpoints')]
class WebhookEndpoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webhookEndpoints')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $publicToken;

    #[ORM\Column(type: 'string', length: 2048)]
    private string $forwardUrl;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'endpoint', targetEntity: WebhookEvent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $events;

    public function __construct(User $user, string $name, string $forwardUrl, ?string $publicToken = null)
    {
        $this->user = $user;
        $this->name = trim($name);
        $this->forwardUrl = trim($forwardUrl);
        $this->publicToken = $publicToken ?? bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function setPublicToken(string $publicToken): self
    {
        $this->publicToken = $publicToken;

        return $this;
    }

    public function getForwardUrl(): string
    {
        return $this->forwardUrl;
    }

    public function setForwardUrl(string $forwardUrl): self
    {
        $this->forwardUrl = trim($forwardUrl);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function getPublicWebhookUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/hooks/' . $this->publicToken;
    }
}
