<?php

namespace App\Tests;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EndToEndWebhookFlowTest extends WebTestCase
{
    private string $backendDir;
    private $backendProcess;

    protected function setUp(): void
    {
        $this->backendDir = __DIR__ . '/fixtures/e2e-backend';
        @unlink($this->backendDir . '/requests.log');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->backendProcess)) {
            proc_terminate($this->backendProcess);
            proc_close($this->backendProcess);
        }

        @unlink($this->backendDir . '/requests.log');
    }

    public function testWebhookIsAcceptedAndDeliveredToARealisticBackend(): void
    {
        $this->startBackend();

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

        $client->request('POST', '/api/endpoints', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-API-Key' => 'capn-hook-dev-key',
        ], content: json_encode([
            'name' => 'Stripe Payments',
            'forward_url' => 'http://127.0.0.1:8123',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $endpoint = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($endpoint['public_token']);

        $client->request('POST', '/hooks/' . $endpoint['public_token'], server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Webhook-Id' => 'evt-42',
            'HTTP_X-Webhook-Event-Type' => 'invoice.paid',
        ], content: json_encode([
            'id' => 'evt-42',
            'customer' => 'acme',
            'amount' => 1500,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(202);
        $ingestion = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('accepted', $ingestion['status']);

        $eventId = (int) $ingestion['event_id'];
        $deadline = microtime(true) + 10;

        do {
            $client->request('GET', '/api/events/' . $eventId, server: [
                'HTTP_X-API-Key' => 'capn-hook-dev-key',
            ]);

            $event = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if ($event['status'] === 'DELIVERED') {
                break;
            }

            usleep(200000);
        } while (microtime(true) < $deadline);

        $this->assertSame('DELIVERED', $event['status']);
        $this->assertSame('invoice.paid', $event['event_type']);
        $this->assertSame('evt-42', $event['external_id']);

        $logContents = file_get_contents($this->backendDir . '/requests.log');
        $this->assertNotFalse($logContents);
        $this->assertStringContainsString('evt-42', $logContents);
        $this->assertStringContainsString('invoice.paid', $logContents);
    }

    private function startBackend(): void
    {
        $this->backendProcess = proc_open(
            ['php', '-S', '127.0.0.1:8123', '-t', $this->backendDir],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($this->backendProcess)) {
            $this->fail('The fake webhook backend could not be started.');
        }

        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $status = proc_get_status($this->backendProcess);
            if (!$status['running']) {
                $this->fail('The fake webhook backend process exited unexpectedly (port possibly already in use).');
            }

            usleep(100000);
            $connection = @fsockopen('127.0.0.1', 8123, $errno, $errstr, 0.2);
            if (is_resource($connection)) {
                fclose($connection);
                break;
            }
        }
    }
}
