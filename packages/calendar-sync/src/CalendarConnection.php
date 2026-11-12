<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use DateTimeZone;
use Phil\CalendarSync\Exception\InvalidSync;

/**
 * A calendar of someone's, linked to the site: its identifier in the application, the adapter that reads or writes it,
 * and the calendar itself in that adapter's terms — the address of a feed or of a CalDAV collection, a Google calendar
 * identifier, a Microsoft calendar identifier or `primary` —, with the time zone of the times a feed writes without
 * one.
 *
 * Its credentials are not part of it: `Contract\CredentialStore` gives them for one call.
 */
final readonly class CalendarConnection
{
    public const int MAX_ID = 128;

    public const int MAX_CALENDAR = 2048;

    public DateTimeZone $timezone;

    /**
     * @param string $provider the name of the adapter: `ics`, `caldav`, `google`, `microsoft`, or one of the application
     */
    public function __construct(
        public string $id,
        public string $provider,
        public string $calendar,
        ?DateTimeZone $timezone = null,
    ) {
        $reason = match (true) {
            '' === $id || \strlen($id) > self::MAX_ID => \sprintf('The identifier of a connection is 1 to %d bytes.', self::MAX_ID),
            1 !== preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $provider) => 'The name of an adapter is a lowercase word of 32 characters at most.',
            '' === $calendar || \strlen($calendar) > self::MAX_CALENDAR => \sprintf('The calendar of a connection is 1 to %d bytes.', self::MAX_CALENDAR),
            1 === preg_match('/[\x00-\x1F\x7F]/', $id.$calendar) => 'A connection holds no control character.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidSync::because($reason);
        }

        $this->timezone = $timezone ?? new DateTimeZone('UTC');
    }
}
