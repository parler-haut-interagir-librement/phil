<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

use Phil\BulkActions\Exception\InvalidInput;

/**
 * The identifier of a run: 16 random bytes in lowercase hexadecimal, the only thing its messages carry.
 */
final readonly class RunId
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(sodium_bin2hex(random_bytes(16)));
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $value)) {
            throw InvalidInput::because('run identifier', 'must be 32 lowercase hexadecimal digits');
        }

        return new self($value);
    }
}
