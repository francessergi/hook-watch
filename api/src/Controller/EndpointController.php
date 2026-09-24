<?php

namespace App\Controller;

use App\Entity\WebhookEndpoint;
use App\Service\WebhookEndpointService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class EndpointController extends AbstractController
{
    public function __construct(private readonly WebhookEndpointService $webhookEndpointService)
    {
    }

    #[Route('/api/endpoints', name: 'api_endpoints_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);

        $payload = $this->readJsonBody($request);
        $name = (string) ($payload['name'] ?? '');
        $forwardUrl = (string) ($payload['forward_url'] ?? '');

        $endpoint = $this->webhookEndpointService->create($name, $forwardUrl);

        return $this->json([
            'id' => $endpoint->getId(),
            'name' => $endpoint->getName(),
            'forward_url' => $endpoint->getForwardUrl(),
            'public_token' => $endpoint->getPublicToken(),
            'public_url' => $endpoint->getPublicWebhookUrl($_ENV['DEFAULT_URI'] ?? 'http://localhost'),
            'active' => $endpoint->isActive(),
            'created_at' => $endpoint->getCreatedAt()->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/endpoints', name: 'api_endpoints_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->assertAdminAccess($request);

        $endpoints = array_map(fn (WebhookEndpoint $endpoint) => [
            'id' => $endpoint->getId(),
            'name' => $endpoint->getName(),
            'forward_url' => $endpoint->getForwardUrl(),
            'public_token' => $endpoint->getPublicToken(),
            'public_url' => $endpoint->getPublicWebhookUrl($_ENV['DEFAULT_URI'] ?? 'http://localhost'),
            'active' => $endpoint->isActive(),
            'created_at' => $endpoint->getCreatedAt()->format(DATE_ATOM),
        ], $this->webhookEndpointService->list());

        return $this->json(['items' => $endpoints]);
    }

    #[Route('/api/endpoints/{id}', name: 'api_endpoints_show', methods: ['GET'])]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $endpoint = $this->webhookEndpointService->find($id);

        if (!$endpoint) {
            throw $this->createNotFoundException('Endpoint not found.');
        }

        return $this->json($this->serializeEndpoint($endpoint));
    }

    #[Route('/api/endpoints/{id}', name: 'api_endpoints_update', methods: ['PATCH'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $endpoint = $this->webhookEndpointService->find($id);

        if (!$endpoint) {
            throw $this->createNotFoundException('Endpoint not found.');
        }

        $payload = $this->readJsonBody($request);
        $this->webhookEndpointService->update(
            $endpoint,
            array_key_exists('name', $payload) ? (string) $payload['name'] : null,
            array_key_exists('forward_url', $payload) ? (string) $payload['forward_url'] : null,
            array_key_exists('active', $payload) ? (bool) $payload['active'] : null,
        );

        return $this->json($this->serializeEndpoint($endpoint));
    }

    #[Route('/api/endpoints/{id}', name: 'api_endpoints_delete', methods: ['DELETE'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        $this->assertAdminAccess($request);
        $endpoint = $this->webhookEndpointService->find($id);

        if (!$endpoint) {
            throw $this->createNotFoundException('Endpoint not found.');
        }

        $this->webhookEndpointService->delete($endpoint);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function serializeEndpoint(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->getId(),
            'name' => $endpoint->getName(),
            'forward_url' => $endpoint->getForwardUrl(),
            'public_token' => $endpoint->getPublicToken(),
            'public_url' => $endpoint->getPublicWebhookUrl($_ENV['DEFAULT_URI'] ?? 'http://localhost'),
            'active' => $endpoint->isActive(),
            'created_at' => $endpoint->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function readJsonBody(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new BadRequestHttpException('The request body must be valid JSON.');
        }

        return $decoded;
    }

    private function assertAdminAccess(Request $request): void
    {
        $apiKey = $_ENV['API_KEY'] ?? 'capn-hook-dev-key';
        if ((string) $request->headers->get('X-API-Key', '') !== $apiKey) {
            throw new AccessDeniedHttpException('Valid X-API-Key header is required.');
        }
    }
}
