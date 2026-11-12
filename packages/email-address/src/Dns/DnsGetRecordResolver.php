<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Dns;

use Closure;
use Override;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\MailDomain;

/**
 * Asks the system's resolver through `dns_get_record()`: the MX records first, then A and AAAA when there is none.
 *
 * `dns_get_record()` blocks for as long as the system's resolver waits, which its own configuration bounds
 * (`timeout` and `attempts` of resolv.conf). The query names the domain with a final dot, so that no search domain of
 * the host is appended.
 */
final readonly class DnsGetRecordResolver implements MailDomainResolver
{
    /** @var Closure(string, int): (list<array<mixed>>|false) */
    private Closure $lookup;

    /**
     * @param (Closure(string, int): (list<array<mixed>>|false))|null $lookup the records of a host and a
     *                                                                        type, or `false` when the lookup failed; `dns_get_record()` by default
     */
    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? $this->system(...);
    }

    #[Override]
    public function resolve(string $asciiDomain): MailDomain
    {
        $host = $asciiDomain.'.';
        $mx = ($this->lookup)($host, \DNS_MX);

        if (false === $mx) {
            return MailDomain::Unknown;
        }

        if ([] !== $mx) {
            // RFC 7505: a single MX record whose exchange is the root.
            $target = $mx[0]['target'] ?? null;

            return 1 === \count($mx) && \in_array($target, ['', '.'], true) ? MailDomain::NullMx : MailDomain::Mx;
        }

        foreach ([\DNS_A, \DNS_AAAA] as $type) {
            $records = ($this->lookup)($host, $type);

            if (false === $records) {
                return MailDomain::Unknown;
            }

            if ([] !== $records) {
                return MailDomain::AddressOnly;
            }
        }

        return MailDomain::NoRecord;
    }

    /**
     * @return list<array<mixed>>|false
     */
    private function system(string $host, int $type): array|false
    {
        // A failed lookup raises a warning as well as returning false: the warning says nothing more.
        return @dns_get_record($host, $type);
    }
}
