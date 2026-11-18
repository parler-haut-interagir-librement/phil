<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;

/**
 * An email domain an organization has verified, in lowercase ASCII — an internationalized domain in its `xn--` form —:
 * labels of 1 to 63 letters, digits or "-", neither starting nor ending with "-", joined by dots, at least two, at
 * most 253 characters in all.
 */
final readonly class EmailDomain
{
    public const int MAX_LENGTH = 253;

    private const string PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidOrganization when the text is not a domain of at least two labels
     */
    public static function fromString(string $value): self
    {
        $domain = strtolower($value);

        if (\strlen($domain) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $domain)) {
            throw InvalidOrganization::because(\sprintf('"%s" is not an email domain of at least two labels, in ASCII.', $value));
        }

        return new self($domain);
    }

    /**
     * The domain of an address, the text after its last "@".
     *
     * @throws InvalidOrganization when the address has no "@" or its domain is out of shape
     */
    public static function ofAddress(string $address): self
    {
        $at = strrpos($address, '@');

        if (false === $at || 0 === $at) {
            throw InvalidOrganization::because('An email address has a local part, an "@" and a domain.');
        }

        return self::fromString(substr($address, $at + 1));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
