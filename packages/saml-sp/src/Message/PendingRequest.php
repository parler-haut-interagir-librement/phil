<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A request sent and not answered yet: its ID, which the response repeats in `InResponseTo`, the identity provider it
 * was sent to, when, where the person goes once signed in, and the hash of the token of the browser that sent it, when
 * the request is bound to it.
 */
final readonly class PendingRequest
{
    public function __construct(
        public string $id,
        public string $identityProvider,
        public DateTimeImmutable $createdAt,
        public ?string $target = null,
        public ?string $browserBinding = null,
    ) {
        if ('' === $id || '' === $identityProvider) {
            throw new InvalidArgumentException('A pending request has an ID and an identity provider.');
        }
    }

    /**
     * @return array{id: string, idp: string, created: int, target: string|null, binding: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'idp' => $this->identityProvider, 'created' => $this->createdAt->getTimestamp(), 'target' => $this->target, 'binding' => $this->browserBinding];
    }

    /**
     * The request written by `toArray()`, null for anything else.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data) || !\is_string($data['id'] ?? null) || !\is_string($data['idp'] ?? null) || !\is_int($data['created'] ?? null)) {
            return null;
        }

        $target = $data['target'] ?? null;
        $binding = $data['binding'] ?? null;

        return new self($data['id'], $data['idp'], new DateTimeImmutable('@'.$data['created']), \is_string($target) ? $target : null, \is_string($binding) ? $binding : null);
    }
}
