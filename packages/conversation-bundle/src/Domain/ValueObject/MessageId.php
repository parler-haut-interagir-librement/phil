<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\ValueObject;

use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Stringable;
use Symfony\Component\Uid\Uuid;

/**
 * A message, named by a UUID, version 7 when the bundle makes it, written in lowercase.
 */
final readonly class MessageId implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    /**
     * @throws InvalidConversation when the text is not a UUID
     */
    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value, Uuid::FORMAT_RFC_4122)) {
            throw InvalidConversation::identifier('message');
        }

        return new self(strtolower($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
