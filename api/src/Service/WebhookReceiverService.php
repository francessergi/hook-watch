<?php

namespace App\Service;

use App\Entity\WebhookEndpoint;
use App\Entity\WebhookEvent;
use App\Message\DeliverWebhookMessage;
use App\Repository\WebhookEndpointRepository;
use App\Repository\WebhookEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookReceiverService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebhookEndpointRepository $webhookEndpointRepository,
        private readonly WebhookEventRepository $webhookEventRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly int $maxPayloadBytes,
    ) {
    }

    public function ingest(string $publicToken, Request $request): WebhookEvent
    {
        $endpoint = $this->webhookEndpointRepository->findOneByPublicToken($publicToken);

        if (!$endpoint || !$endpoint->isActive()) {
            throw new NotFoundHttpException('The requested webhook endpoint does not exist or is inactive.');
        }

        $payload = (string) $request->getContent();
        $contentLength = (int) $request->headers->get('content-length', '0');
        if ($contentLength > $this->maxPayloadBytes || strlen($payload) > $this->maxPayloadBytes) {
            throw new BadRequestHttpException(sprintf('The payload size exceeds the configured limit of %d bytes.', $this->maxPayloadBytes));
        }

        $externalId = $this->extractExternalId($request);
        $existingEvent = $this->webhookEventRepository->findOneForEndpointAndExternalId($endpoint, $externalId);
        if ($existingEvent instanceof WebhookEvent) {
            return $existingEvent;
        }

        $decodedPayload = json_decode($payload, true);
        $event = new WebhookEvent(
            $endpoint,
            $externalId,
            $this->extractEventType($request),
            is_array($decodedPayload) ? $decodedPayload : ['raw' => $payload],
            $request->headers->all()
        );

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new DeliverWebhookMessage($event->getId()));

        return $event;
    }

    private function extractExternalId(Request $request): ?string
    {
        $candidates = [
            'X-Webhook-Id',
            'X-External-Id',
            'X-Event-Id',
            'X-Idempotency-Key',
            'Idempotency-Key',
            'Webhook-Id',
        ];

        foreach ($candidates as $candidate) {
            $headerValue = $request->headers->get($candidate);
            if ($headerValue !== null && trim($headerValue) !== '') {
                return trim($headerValue);
            }
        }

        return null;
    }

    private function extractEventType(Request $request): string
    {
        foreach (['X-Webhook-Event-Type', 'X-Event-Type', 'X-Provider-Event-Type'] as $headerName) {
            $value = $request->headers->get($headerName);
            if ($value !== null && trim($value) !== '') {
                return strtolower((string) trim($value));
            }
        }

        return 'webhook';
    }
}
