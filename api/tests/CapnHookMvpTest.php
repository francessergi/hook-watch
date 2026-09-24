<?php

namespace App\Tests;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CapnHookMvpTest extends WebTestCase
{
    public function testEndpointAndWebhookFlow(): void
    {
        $client = static::createClient();

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        if ($metadata !== []) {
            try {
                $schemaTool->dropSchema($metadata);
            } catch (\Throwable) {
            }

            $schemaTool->createSchema($metadata);
        }

        $client->request('POST', '/api/endpoints', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-API-Key' => 'capn-hook-dev-key',
        ], json_encode([
            'name' => 'Stripe Payments',
            'forward_url' => 'https://example.com/webhooks/stripe',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($payload['public_token']);
        $this->assertSame('Stripe Payments', $payload['name']);

        $publicToken = $payload['public_token'];
        $client->request('POST', '/hooks/' . $publicToken, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Webhook-Id' => 'evt-123',
            'HTTP_X-Webhook-Event-Type' => 'invoice.paid',
        ], json_encode([
            'id' => 'evt-123',
            'amount' => 42,
            'currency' => 'eur',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(202);
        $ingestionPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('accepted', $ingestionPayload['status']);

        $eventId = (int) $ingestionPayload['event_id'];
        $client->request('GET', '/api/events/' . $eventId, server: [
            'HTTP_X-API-Key' => 'capn-hook-dev-key',
        ]);

        $this->assertResponseIsSuccessful();
        $eventPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invoice.paid', $eventPayload['event_type']);
        $this->assertSame('evt-123', $eventPayload['external_id']);
    }
}
