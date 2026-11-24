<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dmarc;

use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\DomainName;

/**
 * The DNS tree walk of RFC 9989 (section 4.10): the `_dmarc` record of a domain, then of its parents, without the
 * Public Suffix List.
 *
 * The walk queries the domain itself, then, for a domain of eight labels or more, the seven rightmost labels, then one
 * label fewer at each step down to the top-level domain: at most eight queries. A name holding no record, several, or
 * one that cannot be read, is skipped.
 */
final readonly class DmarcTreeWalk
{
    public function __construct(
        private DmarcParser $parser = new DmarcParser(),
    ) {
    }

    /**
     * The names the walk queries, in order, without the `_dmarc.` prefix.
     *
     * @return non-empty-list<string>
     */
    public static function names(string $domain): array
    {
        $names = [$domain];
        $count = DomainName::labelCount($domain);

        for ($labels = min($count - 1, 7); $labels >= 1; --$labels) {
            $names[] = DomainName::rightmost($domain, $labels);
        }

        return $names;
    }

    /**
     * The record that applies to mail from the domain: the first one the walk finds.
     */
    public function policy(string $domain, TxtResolver $dns): DmarcDiscovery
    {
        $ignored = [];

        foreach (self::names($domain) as $name) {
            try {
                $record = $this->record($name, $dns);
            } catch (DnsLookupFailed) {
                return new DmarcDiscovery($domain, lookupFailed: true, ignored: $ignored);
            } catch (InvalidRecord $invalid) {
                $ignored[$name] = $invalid->getMessage();

                continue;
            }

            if ($record instanceof DmarcRecord) {
                return new DmarcDiscovery($domain, $record, $name, ignored: $ignored);
            }
        }

        return new DmarcDiscovery($domain, ignored: $ignored);
    }

    /**
     * The organisational domain of a domain (RFC 9989, section 4.10.2): the domain whose record says `psd=n`; the
     * domain one label below a record that says `psd=y`; otherwise the domain of fewest labels that holds a record;
     * the domain itself when none does.
     *
     * @throws DnsLookupFailed
     */
    public function organisationalDomain(string $domain, TxtResolver $dns): string
    {
        $fewest = null;

        foreach (self::names($domain) as $name) {
            try {
                $record = $this->record($name, $dns);
            } catch (InvalidRecord) {
                continue;
            }

            if (!$record instanceof DmarcRecord) {
                continue;
            }

            if (false === $record->publicSuffix) {
                return $name;
            }

            if (true === $record->publicSuffix) {
                return $name === $domain ? $domain : DomainName::rightmost($domain, DomainName::labelCount($name) + 1);
            }

            $fewest = $name;
        }

        return $fewest ?? $domain;
    }

    /**
     * @throws DnsLookupFailed
     * @throws InvalidRecord   when the name holds several records or one that cannot be read
     */
    private function record(string $name, TxtResolver $dns): ?DmarcRecord
    {
        $records = array_values(array_filter($dns->txt('_dmarc.'.$name), DmarcParser::isDmarc(...)));

        if ([] === $records) {
            return null;
        }

        if (\count($records) > 1) {
            throw InvalidRecord::because('it holds more than one DMARC record.');
        }

        return $this->parser->parse($records[0]);
    }
}
