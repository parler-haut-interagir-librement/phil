<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Stringable;

/**
 * The person an access, portability or erasure request is about, by the opaque identifier every module
 * indexes its records on.
 *
 * **Opaque on purpose.** The bundle does not know whether the identifier is a UUID, an OIDC `sub` or a
 * customer number, and it never parses it: a module that needs structure owns that structure. What the bundle
 * refuses is what no module can index on — an empty string, or one carrying whitespace or control characters,
 * which is how an identifier copied from an e-mail arrives with a trailing newline and matches nothing.
 */
final readonly class DataSubject implements Stringable
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidPrivacyValue::because('A data subject identifier cannot be empty.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidPrivacyValue::because(\sprintf('A data subject identifier is at most %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidPrivacyValue::because('A data subject identifier cannot contain whitespace or control characters.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
