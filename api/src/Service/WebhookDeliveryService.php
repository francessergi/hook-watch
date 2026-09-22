<?php

namespace App\Service;

use App\Entity\DeliveryAttempt;
use App\Entity\WebhookEvent;
use App\Message\DeliverWebhookMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class WebhookDeliveryService
{
    private const MAX_ATTEMPTS = 4;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function deliver(WebhookEvent $event, string $attemptType = DeliveryAttempt::TYPE_AUTOMATIC): DeliveryAttempt
    {
        $attemptNumber = $event->getAttemptCount() + 1;
        $attempt = new DeliveryAttempt($event, $attemptNumber, $attemptType);
        $event->addDeliveryAttempt($attempt);
        $event->markProcessing();

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        try {
            $response = HttpClient::create()->request('POST', $event->getEndpoint()->getForwardUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-HookWatch-Event-Id' => (string) $event->getId(),
                    'X-HookWatch-Endpoint-Id' => (string) $event->getEndpoint()->getId(),
                    'X-HookWatch-External-Id' => (string) ($event->getExternalId() ?? ''),
                ],
                'json' => $event->getPayload(),
                'timeout' => 30,
                'max_duration' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            $attempt->finish($statusCode, $body, null);

            if ($statusCode >= 200 && $statusCode < 300) {
                $event->markDelivered();
            } else {
                $event->markFailed();
                if ($this->shouldRetry($statusCode) && $event->getAttemptCount() < self::MAX_ATTEMPTS) {
                    $this->scheduleAutomaticRetry($event);
                } elseif ($event->getAttemptCount() >= self::MAX_ATTEMPTS) {
                    $event->markDeadLetter();
                }
            }
        } catch (\Throwable $exception) {
            $attempt->finish(0, null, $exception->getMessage());
            $event->markFailed();

            if ($event->getAttemptCount() < self::MAX_ATTEMPTS) {
                $this->scheduleAutomaticRetry($event);
            } else {
                $event->markDeadLetter();
            }
        }

        $this->entityManager->flush();

        return $attempt;
    }

    public function retry(WebhookEvent $event): DeliveryAttempt
    {
        return $this->deliver($event, DeliveryAttempt::TYPE_MANUAL_RETRY);
    }

    public function replay(WebhookEvent $event): DeliveryAttempt
    {
        return $this->deliver($event, DeliveryAttempt::TYPE_REPLAY);
    }

    private function shouldRetry(int $statusCode): bool
    {
        return $statusCode === 408 || $statusCode === 429 || $statusCode >= 500;
    }

    private function scheduleAutomaticRetry(WebhookEvent $event): void
    {
        $this->messageBus->dispatch(
            new DeliverWebhookMessage($event->getId(), DeliveryAttempt::TYPE_AUTOMATIC),
            [new DelayStamp(15000)]
        );
    }
}
