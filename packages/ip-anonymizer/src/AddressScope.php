<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

/**
 * What kind of network an address belongs to, from the special-purpose address registries of IANA (RFC 6890 and
 * its updates): the coarsest generalisation, which says nothing of where a public address is.
 */
enum AddressScope: string
{
    /** `0.0.0.0/8`, `::/128`. */
    case Unspecified = 'unspecified';

    /** `127.0.0.0/8`, `::1/128`. */
    case Loopback = 'loopback';

    /** `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, the unique local `fc00::/7` and the old site-local `fec0::/10`. */
    case Private = 'private';

    /** The carrier-grade NAT space `100.64.0.0/10`. */
    case Shared = 'shared';

    /** `169.254.0.0/16`, `fe80::/10`. */
    case LinkLocal = 'link-local';

    /** `192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`, `2001:db8::/32`, `3fff::/20`. */
    case Documentation = 'documentation';

    /** `198.18.0.0/15`, `2001:2::/48`. */
    case Benchmarking = 'benchmarking';

    /** The IPv4/IPv6 translation prefixes `64:ff9b::/96` and `64:ff9b:1::/48`. */
    case Translation = 'translation';

    /** `224.0.0.0/4`, `ff00::/8`. */
    case Multicast = 'multicast';

    /** `192.0.0.0/24`, `240.0.0.0/4` with the broadcast address, and IPv6 outside `2000::/3` and the ranges above. */
    case Reserved = 'reserved';

    /** Every other IPv4 address, and the global unicast IPv6 `2000::/3` outside the ranges above. */
    case Public = 'public';
}
