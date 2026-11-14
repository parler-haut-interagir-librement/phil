<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * Whole numbers and dates typed on the command line.
 *
 * @internal
 */
final class Numbers
{
    private function __construct()
    {
    }

    public static function integer(string $option, string $value): int
    {
        if (1 !== preg_match('/^(?:0|[1-9]\d{0,8})$/D', $value)) {
            throw InvalidValue::because(\sprintf('The %s is a whole number; "%s" is not.', $option, $value));
        }

        return (int) $value;
    }

    /**
     * A day written `YYYY-MM-DD`, at midnight UTC.
     */
    public static function date(string $option, string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw InvalidValue::because(\sprintf('The %s is a day written YYYY-MM-DD; "%s" is not.', $option, $value));
        }

        return $date;
    }
}
