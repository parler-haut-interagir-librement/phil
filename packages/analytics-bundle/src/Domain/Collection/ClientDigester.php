<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Collection;

use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\IpAnonymizer\Contract\SaltProvider;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\Pipeline;
use Phil\IpAnonymizer\Truncation;
use SensitiveParameter;

/**
 * The digest a collection budget is counted on: an IPv4 address, or the /64 of an IPv6 address — a single IPv6 host
 * rotates freely inside its /64, so a per-address budget would not bound it —, keyed by the salt of the period.
 *
 * The salt provider is the application's choice; `Phil\IpAnonymizer\ForgottenSalt` is the one that keeps every period
 * unlinkable. The address is never kept by this object.
 */
final readonly class ClientDigester
{
    public const int IPV4_PREFIX = 32;

    public const int IPV6_PREFIX = 64;

    private Pipeline $pipeline;

    public function __construct(SaltProvider $salts)
    {
        $this->pipeline = new Pipeline(new KeyedHash($salts), new Truncation(self::IPV4_PREFIX, self::IPV6_PREFIX));
    }

    /**
     * @throws InvalidValue when the text is not an IPv4 or IPv6 address
     */
    public function digestOf(#[SensitiveParameter] string $ip): ClientDigest
    {
        $address = IpAddress::tryParse($ip);

        if (!$address instanceof IpAddress) {
            throw InvalidValue::because('The client address is not an IPv4 or IPv6 address.');
        }

        return ClientDigest::fromString($this->pipeline->anonymize($address));
    }
}
