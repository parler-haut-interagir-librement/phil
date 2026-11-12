<?php

declare(strict_types=1);

namespace Phil\ApiContract\Exception;

use UnexpectedValueException;

/**
 * A field that grants or restricts something carried a value this client does not know.
 *
 * Tolerant reading ignores unknown fields and maps unknown enumeration values to a default. For a field of
 * authority (an assurance level, a grant state, a security event type) that default is refusal: a value the
 * client does not understand must never satisfy a requirement.
 */
final class UnknownAuthorityValue extends UnexpectedValueException implements ApiContractException
{
    /**
     * A value read from a response is not trusted: only its first bytes are echoed in the message.
     */
    private const int SHOWN_LENGTH = 64;

    /**
     * @param class-string $enum
     */
    public static function for(string $enum, mixed $value): self
    {
        $shown = match (true) {
            \is_int($value) => \sprintf('%d', $value),
            \is_string($value) => '"'.addcslashes(\strlen($value) > self::SHOWN_LENGTH ? substr($value, 0, self::SHOWN_LENGTH).'...' : $value, "\0..\37\"\\\177..\377").'"',
            default => get_debug_type($value),
        };

        return new self(\sprintf('%s is not a known value of %s; an unknown authority value is refused.', $shown, $enum));
    }
}
