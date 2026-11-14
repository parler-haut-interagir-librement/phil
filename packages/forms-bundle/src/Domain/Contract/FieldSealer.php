<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Contract;

use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use SensitiveParameter;

/**
 * Encrypts the value of a sensitive field before the entry is kept, and decrypts it for whoever reads the entry. The
 * context names where the value lies — the entry and the field —: it is authenticated with the value, so that a
 * sealed value copied to another entry or field does not open.
 */
interface FieldSealer
{
    /**
     * @return string the sealed value, printable ASCII, at most 4 KiB longer than the value
     */
    public function seal(#[SensitiveParameter] string $value, string $context): string;

    /**
     * @throws UnreadableValue when the value was altered, sealed with another context, or under a key no longer known
     */
    public function open(string $sealed, string $context): string;
}
