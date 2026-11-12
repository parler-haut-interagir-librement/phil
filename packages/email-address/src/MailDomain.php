<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

/**
 * What the DNS says of a domain as a destination of mail (RFC 5321, section 5.1, and RFC 7505).
 */
enum MailDomain: string
{
    /** One or more MX records name the servers that receive its mail. */
    case Mx = 'mx';
    /** No MX record, but an A or AAAA record: the host itself receives its mail. */
    case AddressOnly = 'address_only';
    /** A null MX record: the domain receives no mail. */
    case NullMx = 'null_mx';
    /** No MX, A nor AAAA record: no server receives its mail. */
    case NoRecord = 'no_record';
    /** The lookup failed or timed out: nothing is known. */
    case Unknown = 'unknown';

    /**
     * Whether mail can reach the domain, or `null` when the lookup said nothing.
     */
    public function acceptsMail(): ?bool
    {
        return match ($this) {
            self::Mx, self::AddressOnly => true,
            self::NullMx, self::NoRecord => false,
            self::Unknown => null,
        };
    }
}
