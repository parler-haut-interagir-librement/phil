<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;

/**
 * Reads a value a driver gives: a text, a whole number — which some drivers give as text —, or a text or null.
 *
 * @internal
 */
final class Column
{
    private function __construct()
    {
    }

    public static function string(mixed $value): string
    {
        return match (true) {
            \is_string($value) => $value,
            \is_int($value) => (string) $value,
            default => throw InvalidQuestionnaire::because('A stored column holds a text.'),
        };
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value) => (int) $value,
            default => throw InvalidQuestionnaire::because('A stored column holds a whole number.'),
        };
    }
}
