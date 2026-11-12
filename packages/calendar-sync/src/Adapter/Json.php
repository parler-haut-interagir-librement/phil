<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Phil\CalendarSync\Exception\InvalidCalendarData;

/**
 * The JSON of a calendar service: an object, 64 levels deep at most, and its times in RFC 3339.
 *
 * @internal
 */
final class Json
{
    /**
     * @return array<mixed>
     */
    public static function object(string $body): array
    {
        try {
            $value = json_decode($body, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCalendarData::because('An answer of a calendar service is JSON.');
        }

        return \is_array($value) ? $value : throw InvalidCalendarData::because('An answer of a calendar service is a JSON object.');
    }

    /**
     * A time in RFC 3339, with its offset or `Z`, its fraction of a second dropped; or, with a zone, one without an
     * offset read in that zone, as Microsoft Graph writes them.
     */
    public static function moment(mixed $value, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        if (!\is_string($value) || 1 !== preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d{1,9})?(Z|[+-]\d{2}:\d{2})?$/D', $value, $match) || ('' === ($match[2] ?? '') && !$zone instanceof DateTimeZone)) {
            throw InvalidCalendarData::because('A time of a calendar service is written in RFC 3339.');
        }

        $offset = $match[2] ?? '';
        $moment = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $match[1], '' === $offset ? $zone : new DateTimeZone('Z' === $offset ? 'UTC' : $offset));

        if (!$moment instanceof DateTimeImmutable || $moment->format('Y-m-d\TH:i:s') !== $match[1]) {
            throw InvalidCalendarData::because('A time of a calendar service is a moment that exists.');
        }

        return $moment;
    }
}
