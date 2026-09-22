<?php

namespace App\Controller;

use App\Entity\WebhookEvent;
use App\Service\WebhookReceiverService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class WebhookController extends AbstractController
{
    public function __construct(private readonly WebhookReceiverService $webhookReceiverService)
    {
    }

    #[Route('/hooks/{publicToken}', name: 'webhook_ingest', methods: ['POST'])]
    public function ingest(string $publicToken, Request $request): JsonResponse
    {
        $event = $this->webhookReceiverService->ingest($publicToken, $request);

        return new JsonResponse(
            ['status' => 'accepted', 'event_id' => $event->getId()],
            JsonResponse::HTTP_ACCEPTED
        );
    }
}
