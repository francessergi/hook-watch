<?php

namespace App\Entity;

use App\Repository\DeliveryAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryAttemptRepository::class)]
#[ORM\Table(name: 'delivery_attempts')]
class DeliveryAttempt
{
    public const TYPE_AUTOMATIC = 'AUTOMATIC';
    public const TYPE_MANUAL_RETRY = 'MANUAL_RETRY';
    public const TYPE_REPLAY = 'REPLAY';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WebhookEvent::class, inversedBy: 'deliveryAttempts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WebhookEvent $event;

    #[ORM\Column(type: 'integer')]
    private int $attemptNumber;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $httpStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    public function __construct(WebhookEvent $event, int $attemptNumber, string $type)
    {
        $this->event = $event;
        $this->attemptNumber = $attemptNumber;
        $this->type = $type;
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): WebhookEvent
    {
        return $this->event;
    }

    public function setEvent(WebhookEvent $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function finish(int $httpStatus, ?string $responseBody = null, ?string $error = null): void
    {
        $this->httpStatus = $httpStatus;
        $this->responseBody = $responseBody !== null ? mb_substr($responseBody, 0, 2000) : null;
        $this->error = $error !== null ? mb_substr($error, 0, 1000) : null;
        $this->finishedAt = new \DateTimeImmutable();
    }
}
