<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\SamlSp\Exception\InvalidMessage;

/**
 * The instants of SAML: `xs:dateTime` in UTC with the `Z` the profile asks for, fractions of a second read to the
 * microsecond.
 */
final class Instant
{
    private const string PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?Z$/D';

    private function __construct()
    {
    }

    public static function parse(string $text, string $what): DateTimeImmutable
    {
        if (1 !== preg_match(self::PATTERN, $text, $parts)) {
            throw InvalidMessage::because(\sprintf('%s is not an instant in UTC.', $what));
        }

        $micro = substr(str_pad($parts[2] ?? '', 6, '0'), 0, 6);
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u', $parts[1].'.'.$micro, new DateTimeZone('UTC'));

        if (false === $instant || $instant->format('Y-m-d\TH:i:s') !== $parts[1]) {
            throw InvalidMessage::because(\sprintf('%s is not an instant in UTC.', $what));
        }

        return $instant;
    }

    public static function format(DateTimeInterface $instant): string
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
