<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Exception\InvalidCheckin;

/**
 * Where a credential is scanned: a door, a gate, a room, one session of a programme. The application names its checkpoints; a credential lists those it opens, or opens them all.
 *
 * Letters, digits, `.`, `_`, `:` and `-`, at most 64 bytes.
 */
final readonly class CheckpointId
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCheckin
     */
    public static function of(string $value): self
    {
        return new self(Identifier::check($value, 'checkpoint identifier'));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
