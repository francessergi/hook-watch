<?php

namespace App\Entity;

use App\Repository\WebhookEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookEventRepository::class)]
#[ORM\Table(name: 'webhook_events')]
#[ORM\UniqueConstraint(name: 'uniq_endpoint_external_id', columns: ['endpoint_id', 'external_id'])]
class WebhookEvent
{
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_DEAD_LETTER = 'DEAD_LETTER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WebhookEndpoint::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WebhookEndpoint $endpoint;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $eventType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'json')]
    private array $headers = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status = self::STATUS_RECEIVED;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: DeliveryAttempt::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $deliveryAttempts;

    public function __construct(WebhookEndpoint $endpoint, ?string $externalId, string $eventType, array $payload, array $headers)
    {
        $this->endpoint = $endpoint;
        $this->externalId = $externalId !== null && trim($externalId) !== '' ? trim($externalId) : null;
        $this->eventType = $eventType !== '' ? $eventType : 'webhook';
        $this->payload = $payload;
        $this->headers = $headers;
        $this->receivedAt = new \DateTimeImmutable();
        $this->deliveryAttempts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): WebhookEndpoint
    {
        return $this->endpoint;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDeliveryAttempts(): Collection
    {
        return $this->deliveryAttempts;
    }

    public function addDeliveryAttempt(DeliveryAttempt $deliveryAttempt): self
    {
        if (!$this->deliveryAttempts->contains($deliveryAttempt)) {
            $this->deliveryAttempts->add($deliveryAttempt);
            $deliveryAttempt->setEvent($this);
        }

        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->deliveryAttempts->count();
    }

    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
    }

    public function markDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
    }

    public function markDeadLetter(): void
    {
        $this->status = self::STATUS_DEAD_LETTER;
    }
}
