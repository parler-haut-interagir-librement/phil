<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * An identifier another system gives and the bundle keeps as it is, without reading it: the key of a course in a
 * source of completions, or the identifier of a completion. It has from 1 to 255 characters, without spaces nor
 * control characters.
 */
final readonly class OpaqueId
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param string $label what the identifier is, for the message of a refusal
     */
    public static function fromString(string $value, string $label): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $value)) {
            throw InvalidValue::because(\sprintf('The %s has from 1 to %d characters, without spaces nor control characters.', $label, self::MAX_LENGTH));
        }

        return new self($value);
    }
}
