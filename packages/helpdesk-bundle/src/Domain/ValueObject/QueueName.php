<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\ValueObject;

use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Stringable;

/**
 * The name of a queue, as the configuration declares it: 1 to 64 lowercase letters, digits, dashes and underscores.
 */
final readonly class QueueName implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidQueue when the name is malformed
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z0-9_-]{1,64}$/D', $value)) {
            throw InvalidQueue::malformed();
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
