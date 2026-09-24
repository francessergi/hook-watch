<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebhookEndpoint;
use App\Repository\UserRepository;
use App\Repository\WebhookEndpointRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WebhookEndpointService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly WebhookEndpointRepository $webhookEndpointRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function create(string $name, string $forwardUrl, ?string $email = null): WebhookEndpoint
    {
        $name = trim($name);
        $forwardUrl = trim($forwardUrl);

        if ($name === '') {
            throw new BadRequestHttpException('The endpoint name is required.');
        }

        $violations = $this->validator->validate($forwardUrl, [new Url(normalizer: 'trim', requireTld: false)]);
        if (count($violations) > 0) {
            throw new BadRequestHttpException('The forward URL is not a valid HTTP(S) URL.');
        }

        $user = $this->userRepository->findOrCreateDefaultUser($email ?? 'admin@capn-hook.local');
        $endpoint = new WebhookEndpoint($user, $name, $forwardUrl);

        $this->entityManager->persist($endpoint);
        $this->entityManager->flush();

        return $endpoint;
    }

    public function list(): array
    {
        return $this->webhookEndpointRepository->findBy([], ['createdAt' => 'DESC']);
    }

    public function find(int $id): ?WebhookEndpoint
    {
        return $this->webhookEndpointRepository->find($id);
    }

    public function update(WebhookEndpoint $endpoint, ?string $name, ?string $forwardUrl, ?bool $active): WebhookEndpoint
    {
        if ($name !== null && trim($name) !== '') {
            $endpoint->setName($name);
        }

        if ($forwardUrl !== null && trim($forwardUrl) !== '') {
            $violations = $this->validator->validate($forwardUrl, [new Url(normalizer: 'trim', requireTld: false)]);
            if (count($violations) > 0) {
                throw new BadRequestHttpException('The forward URL is not a valid HTTP(S) URL.');
            }

            $endpoint->setForwardUrl($forwardUrl);
        }

        if ($active !== null) {
            $endpoint->setActive($active);
        }

        $this->entityManager->flush();

        return $endpoint;
    }

    public function delete(WebhookEndpoint $endpoint): void
    {
        $this->entityManager->remove($endpoint);
        $this->entityManager->flush();
    }
}
