<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * A participant, named by the stable identifier of their account: 1 to 190 letters, digits, `.`, `_`, `:` or `-`. An
 * e-mail address is refused: the identifier travels to the conferencing server, comes back in its notifications and
 * is kept with the attendance, where a personal value would leak.
 */
final readonly class ParticipantId
{
    public const int MAX_LENGTH = 190;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidValue when the value is not an identifier, an e-mail address included
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,190}$/D', $value)) {
            throw InvalidValue::because('A participant is 1 to 190 letters, digits, ".", "_", ":" or "-", never an e-mail address.');
        }

        return new self($value);
    }

    /**
     * The identifier, or null when the value is not one: what a notification names may be anything.
     */
    public static function tryFrom(mixed $value): ?self
    {
        return \is_string($value) && 1 === preg_match('/^[A-Za-z0-9._:-]{1,190}$/D', $value) ? new self($value) : null;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
