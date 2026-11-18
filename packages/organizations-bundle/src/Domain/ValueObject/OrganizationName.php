<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;

/**
 * The display name of an organization or a team: UTF-8 text, trimmed, 1 to 200 characters, without control
 * characters. It is shown as text and escaped on output like any text a member typed.
 */
final readonly class OrganizationName
{
    public const int MAX_LENGTH = 200;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidOrganization when the name is empty once trimmed, too long, not UTF-8 or holds a control character
     */
    public static function fromString(string $value): self
    {
        $name = trim($value);

        if (1 !== preg_match('/^[^\p{Cc}]+$/Du', $name)) {
            throw InvalidOrganization::because('A name is UTF-8 text of at least one character, without control characters.');
        }

        if (mb_strlen($name, 'UTF-8') > self::MAX_LENGTH) {
            throw InvalidOrganization::because(\sprintf('A name is at most %d characters.', self::MAX_LENGTH));
        }

        return new self($name);
    }
}
