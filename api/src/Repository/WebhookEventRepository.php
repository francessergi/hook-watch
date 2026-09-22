<?php

namespace App\Repository;

use App\Entity\WebhookEndpoint;
use App\Entity\WebhookEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WebhookEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEvent::class);
    }

    public function findOneForEndpointAndExternalId(WebhookEndpoint $endpoint, ?string $externalId): ?WebhookEvent
    {
        if ($externalId === null || trim($externalId) === '') {
            return null;
        }

        return $this->findOneBy([
            'endpoint' => $endpoint,
            'externalId' => trim($externalId),
        ]);
    }
}
