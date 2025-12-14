<?php

namespace App\Message;

final class EventMessage
{
    private ?string $type = null;
    private array $payload = [];
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, array $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}