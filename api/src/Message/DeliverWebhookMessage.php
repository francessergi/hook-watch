<?php

namespace App\Message;

final class DeliverWebhookMessage
{
    public function __construct(
        private int $eventId,
        private string $attemptType = 'AUTOMATIC'
    ) {
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function getAttemptType(): string
    {
        return $this->attemptType;
    }
}
