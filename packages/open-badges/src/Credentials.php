<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use DateTimeImmutable;
use DateTimeZone;
use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * What every credential the library builds shares: an identifier that is a `urn:uuid:` or an absolute HTTP URL, and a
 * period of validity written in UTC — and the one reader of the moments of a period, for the issuers and the verifier
 * alike.
 *
 * @internal
 */
final class Credentials
{
    /**
     * A moment of RFC 3339 as an XML Schema `dateTimeStamp` writes it: an upper-case `T`, seconds, an optional fraction,
     * and `Z` or an offset.
     */
    private const string MOMENT = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(?:Z|[+-](\d{2}):(\d{2}))$/D';

    private function __construct()
    {
    }

    public static function checkId(string $id): void
    {
        if (1 !== preg_match('#^(urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|https?://[^\s<>"{}|\\\\^`]+)$#D', $id)) {
            throw InvalidBadge::because(\sprintf('The identifier of a credential is a "urn:uuid:" or an absolute HTTP URL; "%s" is not.', $id));
        }
    }

    /**
     * The `validFrom` and, when given, `validUntil` fields.
     *
     * @return array{validFrom: string, validUntil?: string}
     */
    public static function period(DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil): array
    {
        if (!$validUntil instanceof DateTimeImmutable) {
            return ['validFrom' => self::moment($validFrom)];
        }

        if ($validUntil <= $validFrom) {
            throw InvalidBadge::because('A credential is valid until a moment after it becomes valid.');
        }

        return ['validFrom' => self::moment($validFrom), 'validUntil' => self::moment($validUntil)];
    }

    public static function moment(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * The moment a `validFrom` or a `validUntil` names, read strictly: each part within its range — no 30 February, no
     * hour 24, no leap second, no offset beyond 23:59 —, so that no date is carried over to another; the fraction is kept
     * to the microsecond. Null when the text is no such moment.
     */
    public static function parseMoment(string $moment): ?DateTimeImmutable
    {
        if (1 !== preg_match(self::MOMENT, $moment, $parts, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        [, $year, $month, $day, $hour, $minute, $second, $fraction, $offsetHours, $offsetMinutes] = $parts;

        if (!checkdate((int) $month, (int) $day, (int) $year) || (int) $hour > 23 || (int) $minute > 59 || (int) $second > 59 || (int) $offsetHours > 23 || (int) $offsetMinutes > 59) {
            return null;
        }

        // Every part is within its range: PHP reads the moment as written, with no warning and nothing carried over.
        $offset = null === $offsetHours ? '+00:00' : substr($moment, -6);

        return new DateTimeImmutable(\sprintf('%s-%s-%sT%s:%s:%s.%s%s', $year, $month, $day, $hour, $minute, $second, substr(str_pad($fraction ?? '', 6, '0'), 0, 6), $offset));
    }
}
