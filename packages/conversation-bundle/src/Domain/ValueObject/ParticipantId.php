<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\ValueObject;

use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Stringable;

/**
 * A participant, named by the stable identifier the application gives its members: 1 to 190 characters, without
 * spaces nor control characters, and never an email address — an identifier with `@` is refused, since an address
 * changes and says who the person is.
 */
final readonly class ParticipantId implements Stringable
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidParticipant when the identifier is malformed or holds an `@`
     */
    public static function fromString(string $value): self
    {
        if (str_contains($value, '@')) {
            throw InvalidParticipant::looksLikeAnEmail();
        }

        if (1 !== preg_match('/^[^\s\p{C}]{1,'.self::MAX_LENGTH.'}$/uD', $value)) {
            throw InvalidParticipant::malformed(self::MAX_LENGTH);
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
