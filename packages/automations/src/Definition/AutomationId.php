<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;
use Stringable;

/**
 * The stable name of an automation, kept across its versions: `welcome-series`, `order.follow-up`.
 */
final readonly class AutomationId implements Stringable
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidDefinition when the value is not a lowercase name of at most 64 characters
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not an automation id: a lowercase letter or digit, then up to 63 lowercase letters, digits, "_", "." or "-".', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
