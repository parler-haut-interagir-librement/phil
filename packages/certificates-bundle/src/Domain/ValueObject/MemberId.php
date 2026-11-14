<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * The stable identifier the application gives a member — a UUID, a number, a handle that never changes —, by which its
 * certificates and credits of continuing education are counted. An e-mail address is refused: it changes, and it is
 * personal data the bundle does not keep.
 */
final readonly class MemberId
{
    public const int MAX_LENGTH = 191;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $member): self
    {
        if ('' === $member || \strlen($member) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $member)) {
            throw InvalidValue::because(\sprintf('The identifier of a member has from 1 to %d characters, without spaces nor control characters.', self::MAX_LENGTH));
        }

        if (str_contains($member, '@')) {
            throw InvalidValue::because('The identifier of a member is a stable identifier, not an e-mail address.');
        }

        return new self($member);
    }
}
