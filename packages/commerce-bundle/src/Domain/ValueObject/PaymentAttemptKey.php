<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The key of one payment attempt limiter state: an order, or a client IP address.
 *
 * Both keys are evaluated for every attempt, and the more restrictive decision wins.
 *
 * **An IP key is the client's network, not its address.** An IPv6 client holds at least a /64 and can change
 * address on every attempt, so an IPv6 address is reduced to its prefix — a /64 unless the caller chooses
 * otherwise — and every address in it shares one state. An IPv4-mapped IPv6 address (`::ffff:203.0.113.7`) is
 * the IPv4 address it maps. Both are normalised through their binary form, so that two spellings share one
 * state.
 *
 * An order identifier is refused when it is empty, padded with whitespace, longer than 255 bytes, or carries a
 * control character: the key ends up in a key-value store.
 */
final readonly class PaymentAttemptKey
{
    private const string ORDER = 'order';

    private const string CLIENT_IP = 'ip';

    public const int DEFAULT_IPV6_PREFIX_LENGTH = 64;

    private const int MAX_ORDER_ID_LENGTH = 255;

    /**
     * The byte that ends a prefix, by how many of its bits the prefix covers.
     */
    private const string PARTIAL_BYTE_MASKS = "\x00\x80\xC0\xE0\xF0\xF8\xFC\xFE";

    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xFF\xFF";

    private function __construct(
        public string $value,
    ) {
    }

    public static function forOrder(string $orderId): self
    {
        if ('' === $orderId || $orderId !== trim($orderId) || \strlen($orderId) > self::MAX_ORDER_ID_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $orderId)) {
            throw InvalidValue::because(\sprintf('An order key needs a non-empty order identifier of at most %d bytes, without surrounding whitespace or control characters.', self::MAX_ORDER_ID_LENGTH));
        }

        return new self(self::ORDER.':'.$orderId);
    }

    /**
     * @param int $ipv6PrefixLength the IPv6 prefix that makes one client, from 1 to 128
     */
    public static function forClientIp(string $ip, int $ipv6PrefixLength = self::DEFAULT_IPV6_PREFIX_LENGTH): self
    {
        if ($ipv6PrefixLength < 1 || $ipv6PrefixLength > 128) {
            throw InvalidValue::because(\sprintf('An IPv6 prefix length is between 1 and 128; %d is not.', $ipv6PrefixLength));
        }

        $binary = false === filter_var($ip, \FILTER_VALIDATE_IP) ? false : inet_pton($ip);
        $suffix = '';

        if (false !== $binary && str_starts_with($binary, self::IPV4_MAPPED_PREFIX)) {
            $binary = substr($binary, \strlen(self::IPV4_MAPPED_PREFIX));
        } elseif (false !== $binary && 16 === \strlen($binary)) {
            $binary = self::masked($binary, $ipv6PrefixLength);
            $suffix = '/'.$ipv6PrefixLength;
        }

        $normalised = false === $binary ? false : inet_ntop($binary);

        if (false === $normalised) {
            throw InvalidValue::because(\sprintf('"%s" is not an IP address.', $ip));
        }

        return new self(self::CLIENT_IP.':'.$normalised.$suffix);
    }

    /**
     * The address with every bit past the prefix set to zero.
     */
    private static function masked(string $binary, int $prefixLength): string
    {
        $mask = str_repeat("\xFF", intdiv($prefixLength, 8)).self::PARTIAL_BYTE_MASKS[$prefixLength % 8];

        return $binary & str_pad($mask, \strlen($binary), "\0");
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
