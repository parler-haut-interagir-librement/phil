<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Phil\DoctrineBehaviours\Contract\VersionCipher;
use RuntimeException;

/**
 * Seals a value by binding it to its context and reversing it: no secrecy, only what the tests check — the sealed
 * value differs from the plain one, and it opens under its own context only.
 */
final class ReversingCipher implements VersionCipher
{
    public function seal(string $plaintext, string $context): string
    {
        return strrev($context.'#'.$plaintext);
    }

    public function open(string $sealed, string $context): string
    {
        $opened = strrev($sealed);

        if (!str_starts_with($opened, $context.'#')) {
            throw new RuntimeException('The value does not open under this context.');
        }

        return substr($opened, \strlen($context) + 1);
    }
}
