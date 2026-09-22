<?php

namespace App\Controller;

use App\Entity\DeliveryAttempt;
use App\Entity\WebhookEvent;
use App\Service\WebhookDeliveryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class EventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    #[Route('/api/events', name: 'api_events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);
        $repository = $this->entityManager->getRepository(WebhookEvent::class);
        $events = $repository->findBy([], ['receivedAt' => 'DESC'], 50);

        return $this->json([
            'items' => array_map(fn (WebhookEvent $event) => $this->serializeEvent($event), $events),
        ]);
    }

    #[Route('/api/events/{id}', name: 'api_events_show', methods: ['GET'])]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $event = $this->entityManager->getRepository(WebhookEvent::class)->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        return $this->json($this->serializeEvent($event));
    }

    #[Route('/api/events/{id}/retry', name: 'api_events_retry', methods: ['POST'])]
    public function retry(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $event = $this->entityManager->getRepository(WebhookEvent::class)->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $attempt = $this->webhookDeliveryService->retry($event);

        return $this->json([
            'event_id' => $event->getId(),
            'attempt_id' => $attempt->getId(),
            'type' => $attempt->getType(),
            'status' => $event->getStatus(),
        ]);
    }

    #[Route('/api/events/{id}/replay', name: 'api_events_replay', methods: ['POST'])]
    public function replay(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $event = $this->entityManager->getRepository(WebhookEvent::class)->find($id);

        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        $attempt = $this->webhookDeliveryService->replay($event);

        return $this->json([
            'event_id' => $event->getId(),
            'attempt_id' => $attempt->getId(),
            'type' => $attempt->getType(),
            'status' => $event->getStatus(),
        ]);
    }

    private function serializeEvent(WebhookEvent $event): array
    {
        return [
            'id' => $event->getId(),
            'endpoint_id' => $event->getEndpoint()->getId(),
            'endpoint_name' => $event->getEndpoint()->getName(),
            'external_id' => $event->getExternalId(),
            'event_type' => $event->getEventType(),
            'status' => $event->getStatus(),
            'received_at' => $event->getReceivedAt()->format(DATE_ATOM),
            'payload' => $event->getPayload(),
            'headers' => $event->getHeaders(),
            'attempts' => array_map(static fn (DeliveryAttempt $attempt) => [
                'id' => $attempt->getId(),
                'attempt_number' => $attempt->getAttemptNumber(),
                'type' => $attempt->getType(),
                'started_at' => $attempt->getStartedAt()->format(DATE_ATOM),
                'finished_at' => $attempt->getFinishedAt()?->format(DATE_ATOM),
                'http_status' => $attempt->getHttpStatus(),
                'response_body' => $attempt->getResponseBody(),
                'error' => $attempt->getError(),
            ], $event->getDeliveryAttempts()->toArray()),
        ];
    }

    private function assertAdminAccess(Request $request): void
    {
        $apiKey = $_ENV['API_KEY'] ?? 'hookwatch-dev-key';
        if ((string) $request->headers->get('X-API-Key', '') !== $apiKey) {
            throw new AccessDeniedHttpException('Valid X-API-Key header is required.');
        }
    }
}
