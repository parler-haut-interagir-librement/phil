<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;

/**
 * `/.well-known/gpc.json` (W3C, Global Privacy Control): the site says it honours the signal, and since when its
 * representation holds, as an RFC 3339 full-date such as `2026-11-21`.
 */
final readonly class GpcSupport
{
    private const string FULL_DATE = '/^(\d{4})-(\d{2})-(\d{2})$/D';

    private function __construct(
        public string $lastUpdate,
    ) {
    }

    /**
     * @throws InvalidValue when the date is not an RFC 3339 full-date of the calendar
     */
    public static function since(string $lastUpdate): self
    {
        if (1 !== preg_match(self::FULL_DATE, $lastUpdate, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw InvalidValue::because(\sprintf('The last_update of gpc is an RFC 3339 full-date, such as "2026-11-21": "%s".', $lastUpdate));
        }

        return new self($lastUpdate);
    }

    /**
     * @return array{gpc: true, lastUpdate: string}
     */
    public function toArray(): array
    {
        return ['gpc' => true, 'lastUpdate' => $this->lastUpdate];
    }
}
