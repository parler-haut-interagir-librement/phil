<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Key;

use Override;
use Phil\RateLimitBundle\Application\Contract\AddressNormalizer;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * The `ip` strategy: the client's address, as Symfony reads it behind the trusted proxies, kept as a keyed hash —
 * `ip:` and the HMAC-SHA-256 of the address, normalized first, under the bundle's secret. The storage never holds an
 * address, and the same client gives the same key from one request to the next.
 *
 * An IPv4 address written as an IPv6 one, `::ffff:203.0.113.7`, as a dual-stack socket reports it, counts as the IPv4
 * address it holds. Without a normalizer, an IPv6 address counts by its /64, the network one subscriber is given: a client that changes
 * its host within it keeps its key. An IPv4 address counts whole.
 */
final readonly class ClientIpKey implements KeyResolver
{
    /** The first twelve bytes of an IPv4-mapped IPv6 address. */
    private const string MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xFF\xFF";

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private ?AddressNormalizer $normalizer = null,
    ) {
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        $address = $request->getClientIp();

        if (null === $address) {
            return null;
        }

        $address = $this->unmapped($address);
        $normalized = $this->normalizer?->normalize($address) ?? IpUtils::anonymize($address, 0, 8);

        return 'ip:'.hash_hmac('sha256', $normalized, $this->secret);
    }

    /**
     * The IPv4 address an IPv4-mapped IPv6 address holds; any other address as it is.
     */
    private function unmapped(string $address): string
    {
        $packed = @inet_pton($address);

        if (!\is_string($packed) || !str_starts_with($packed, self::MAPPED_PREFIX)) {
            return $address;
        }

        return (string) inet_ntop(substr($packed, 12));
    }
}
