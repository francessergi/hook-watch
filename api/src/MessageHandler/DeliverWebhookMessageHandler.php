<?php

namespace App\MessageHandler;

use App\Entity\WebhookEvent;
use App\Message\DeliverWebhookMessage;
use App\Service\WebhookDeliveryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeliverWebhookMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebhookDeliveryService $deliveryService,
    ) {
    }

    public function __invoke(DeliverWebhookMessage $message): void
    {
        $event = $this->entityManager->getRepository(WebhookEvent::class)->find($message->getEventId());

        if (!$event) {
            return;
        }

        $event->markProcessing();
        $this->entityManager->flush();

        $this->deliveryService->deliver($event, $message->getAttemptType());
    }
}
