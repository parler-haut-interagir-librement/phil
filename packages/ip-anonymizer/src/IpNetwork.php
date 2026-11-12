<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Phil\IpAnonymizer\Exception\InvalidIpAddress;
use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\Internal\PrefixMask;
use Stringable;

/**
 * A network: an address whose host bits are clear, and the length of its prefix.
 */
final readonly class IpNetwork implements Stringable
{
    private function __construct(
        public IpAddress $address,
        public int $prefixLength,
    ) {
    }

    /**
     * The network of the given prefix length that holds the address: its host bits are cleared.
     *
     * @throws InvalidNetwork when the length is outside the family's 0 to 32 or 0 to 128
     */
    public static function containing(IpAddress $address, int $prefixLength): self
    {
        $mask = PrefixMask::of($address->family, $prefixLength);

        return new self(IpAddress::fromBytes($address->bytes & $mask), $prefixLength);
    }

    /**
     * A network written `<address>/<length>`, such as `192.0.2.0/24` or `2001:db8::/32`. The address is the network's
     * own: an address with host bits set (`192.0.2.1/24`) is refused, being most often a slip.
     *
     * @throws InvalidNetwork when the text is not a network in CIDR notation
     */
    public static function parse(string $cidr): self
    {
        $parts = explode('/', $cidr);

        if (2 !== \count($parts) || 1 !== preg_match('/^(?:0|[1-9]\d{0,2})$/D', $parts[1])) {
            throw InvalidNetwork::because('A network is written "<address>/<prefix length>".');
        }

        try {
            $address = IpAddress::parse($parts[0]);
        } catch (InvalidIpAddress) {
            throw InvalidNetwork::because('The address of a network is an IPv4 or IPv6 address.');
        }

        $network = self::containing($address, (int) $parts[1]);

        if (!$network->address->equals($address)) {
            throw InvalidNetwork::because('The address of a network has its host bits clear.');
        }

        return $network;
    }

    public function contains(IpAddress $address): bool
    {
        return $address->family === $this->address->family
            && self::containing($address, $this->prefixLength)->address->equals($this->address);
    }

    public function equals(self $other): bool
    {
        return $this->prefixLength === $other->prefixLength && $this->address->equals($other->address);
    }

    public function toString(): string
    {
        return $this->address->toString().'/'.$this->prefixLength;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
