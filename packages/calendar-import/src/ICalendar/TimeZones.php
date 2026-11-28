<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use DateTimeZone;
use IntlTimeZone;

/**
 * The zones the `TZID` of one calendar name, resolved in this order:
 *
 * 1. an IANA name PHP knows, written in any case, with or without the `/` of a globally unique name;
 * 2. a Windows name, as Outlook writes them, through ICU;
 * 3. the `VTIMEZONE` of that `TZID` in the text, when it is simple: its `X-LIC-LOCATION` is an IANA name, or the
 *    `TZID` ends with one (`/mozilla.org/20050126_1/America/Toronto`), or every observance it holds has the same
 *    offset, read as a fixed offset.
 *
 * Anything else is unknown: the rules of a `VTIMEZONE` are never followed to make up a zone. One instance serves one
 * read, and is dropped with it.
 *
 * @internal
 */
final class TimeZones
{
    /** @var array<string, string> the IANA names, by their lowercase form */
    private readonly array $names;

    /** @var array<string, ?DateTimeZone> */
    private array $resolved = [];

    /**
     * @param array<string, Component> $definitions the `VTIMEZONE` components of the calendar, by `TZID`
     */
    public function __construct(
        private readonly array $definitions = [],
    ) {
        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
        $this->names = array_combine(array_map(strtolower(...), $identifiers), $identifiers);
    }

    public function resolve(string $tzid): ?DateTimeZone
    {
        return $this->resolved[$tzid] ??= $this->find($tzid);
    }

    /**
     * An IANA name PHP knows, in any case, or null.
     */
    public function iana(string $name): ?DateTimeZone
    {
        $known = $this->names[strtolower(ltrim(trim($name), '/'))] ?? null;

        return null === $known ? null : new DateTimeZone($known);
    }

    private function find(string $tzid): ?DateTimeZone
    {
        if (\strlen($tzid) > 255) {
            return null;
        }

        // ICU gives false for a name it does not know, whatever its stubs say: false is no IANA name.
        $windows = IntlTimeZone::getIDForWindowsID(trim($tzid));

        return $this->iana($tzid)
            ?? (\in_array($windows, $this->names, true) ? new DateTimeZone($windows) : null)
            ?? $this->defined($tzid);
    }

    private function defined(string $tzid): ?DateTimeZone
    {
        $definition = $this->definitions[$tzid] ?? null;
        $location = $definition?->all('X-LIC-LOCATION')[0] ?? null;
        $zone = null === $location ? null : $this->iana($location->value);
        $segments = explode('/', $tzid);

        for ($from = 1, $count = \count($segments); $from < $count && !$zone instanceof DateTimeZone; ++$from) {
            $zone = $this->iana(implode('/', \array_slice($segments, $from)));
        }

        return $zone ?? ($definition instanceof Component ? $this->fixed($definition) : null);
    }

    /**
     * A zone of one offset: every `STANDARD` and `DAYLIGHT` observance offsets to the same time, `+hhmm` or `-hhmm`.
     */
    private function fixed(Component $definition): ?DateTimeZone
    {
        $offsets = [];

        foreach ($definition->children as $observance) {
            foreach ($observance->all('TZOFFSETTO') as $line) {
                $offsets[$line->value] = true;
            }
        }

        $offset = array_key_first($offsets);

        // An offset of 18 hours at most, as RFC 5545 writes it: +hhmm or +hhmmss, the seconds at 00.
        if (1 !== \count($offsets) || 1 !== preg_match('/^([+-](?:0\d|1[0-8])[0-5]\d)(?:00)?$/D', (string) $offset, $match)) {
            return null;
        }

        return new DateTimeZone($match[1]);
    }
}
