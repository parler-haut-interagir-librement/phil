<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use DateTimeImmutable;
use DateTimeZone;
use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * What every credential the library builds shares: an identifier that is a `urn:uuid:` or an absolute HTTP URL, and a
 * period of validity written in UTC.
 *
 * @internal
 */
final class Credentials
{
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
}
