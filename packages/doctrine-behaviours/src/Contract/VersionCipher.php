<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use RuntimeException;

/**
 * Seals the personal fields of a frozen version, under `PersonalFieldPolicy::Encrypt`, and opens them as the record
 * goes back to it. The context names the record, the version and the field: a cipher binds it to the sealed value as
 * associated data, so that a value moved to another field or another record does not open.
 */
interface VersionCipher
{
    public function seal(string $plaintext, string $context): string;

    /**
     * @throws RuntimeException when the value does not open under that context
     */
    public function open(string $sealed, string $context): string;
}
