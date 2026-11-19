<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * A room, named by an identifier the application chooses or the library draws: 2 to 64 lowercase letters, digits, `-`
 * or `_`, starting with a letter or a digit. It is the meeting identifier of BigBlueButton and the room name of Jitsi,
 * which compares names without case: a lowercase identifier is the same room on both. A Jitsi room is reached by its
 * name, so a drawn identifier is the one to keep where the server lets anyone in.
 */
final readonly class RoomId
{
    public const int MAX_LENGTH = 64;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidValue when the value is not an identifier of a room
     */
    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/D', $value)) {
            throw InvalidValue::because('A room is 2 to 64 lowercase letters, digits, "-" or "_", starting with a letter or a digit.');
        }

        return new self($value);
    }

    /**
     * 32 hexadecimal digits from 16 random bytes: a name nobody guesses.
     */
    public static function generate(): self
    {
        return new self(sodium_bin2hex(random_bytes(16)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
