<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dns;

use Closure;
use Override;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;

/**
 * Asks the system's resolver through `dns_get_record()`, which follows CNAME records.
 *
 * The query names the host with a final dot, so that no search domain of the host is appended. `dns_get_record()`
 * blocks for as long as the system's resolver waits, which its own configuration bounds (`timeout` and `attempts` of
 * resolv.conf). At most 32 records of a name are read.
 */
final readonly class DnsGetRecordResolver implements TxtResolver
{
    private const int MAX_RECORDS = 32;

    /** @var Closure(string, int): (array<mixed>|false) */
    private Closure $lookup;

    /**
     * @param (Closure(string, int): (array<mixed>|false))|null $lookup the records of a host and a type, or `false`
     *                                                                  when the lookup failed; `dns_get_record()` by
     *                                                                  default
     */
    public function __construct(?Closure $lookup = null)
    {
        $this->lookup = $lookup ?? $this->system(...);
    }

    #[Override]
    public function txt(string $asciiName): array
    {
        $records = ($this->lookup)($asciiName.'.', \DNS_TXT);

        if (false === $records) {
            throw DnsLookupFailed::for($asciiName);
        }

        $texts = [];

        foreach (\array_slice($records, 0, self::MAX_RECORDS) as $record) {
            if (!\is_array($record)) {
                continue;
            }

            $entries = $record['entries'] ?? null;

            if (\is_array($entries)) {
                $texts[] = implode('', array_filter($entries, \is_string(...)));
            } elseif (\is_string($record['txt'] ?? null)) {
                $texts[] = $record['txt'];
            }
        }

        return $texts;
    }

    /**
     * @return array<mixed>|false
     */
    private function system(string $host, int $type): array|false
    {
        // A failed lookup raises a warning as well as returning false: the warning says nothing more.
        return @dns_get_record($host, $type);
    }
}
