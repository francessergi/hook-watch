<?php

namespace App\Repository;

use App\Entity\WebhookEndpoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WebhookEndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEndpoint::class);
    }

    public function findOneByPublicToken(string $publicToken): ?WebhookEndpoint
    {
        return $this->findOneBy(['publicToken' => $publicToken]);
    }
}
