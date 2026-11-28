<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

use DateInvalidTimeZoneException;
use DateTimeZone;
use Phil\CalendarImport\Exception\InvalidSource;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * A calendar the application imports: its identifier in the application, its address, and the zone of the times it
 * writes without one, when it names none itself.
 */
final readonly class CalendarSource
{
    public const int MAX_ID_BYTES = 255;

    private function __construct(
        public string $id,
        public Url $url,
        public DateTimeZone $zone,
    ) {
    }

    /**
     * A source by its address: `webcal://` and `webcals://` are read as `https://`, then the address is checked by
     * `phil/url` — `http` or `https`, a public host name, no user name nor password.
     *
     * @throws InvalidSource
     */
    public static function of(string $id, string $address, DateTimeZone|string $zone = 'UTC'): self
    {
        if ('' === $id || \strlen($id) > self::MAX_ID_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            throw InvalidSource::because(\sprintf('The identifier of a source is 1 to %d bytes, on one line.', self::MAX_ID_BYTES));
        }

        $address = preg_replace('~^\s*webcals?://~i', 'https://', $address) ?? $address;

        try {
            $url = Url::fromString($address);
        } catch (UrlException $urlException) {
            throw InvalidSource::because(\sprintf('The address of the source "%s" is not a public web address.', $id), $urlException);
        }

        try {
            $zone = \is_string($zone) ? new DateTimeZone($zone) : $zone;
        } catch (DateInvalidTimeZoneException $dateInvalidTimeZoneException) {
            throw InvalidSource::because(\sprintf('The zone of the source "%s" is not one PHP knows.', $id), $dateInvalidTimeZoneException);
        }

        return new self($id, $url, $zone);
    }
}
