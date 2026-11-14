<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The name a module answers subject requests under: the key of its section in an export, and the name an
 * erasure report and a failure are attributed to.
 */
final readonly class ModuleName
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $value)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a module name: lowercase letters, digits, "_", "." and "-", starting with a letter, at most 64 characters.', $value));
        }

        return new self($value);
    }
}
