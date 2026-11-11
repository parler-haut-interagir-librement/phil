<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Recurrence\Exception\RecurrenceException;
use Phil\Recurrence\RecurrenceRule;

/**
 * The column type `phil_rrule`: a `RecurrenceRule` in a `VARCHAR(255)`, as RFC 5545 writes it — its end in UTC or as
 * a day, so that it reads back the same.
 */
final class RecurrenceRuleColumnType extends Type
{
    public const string NAME = 'phil_rrule';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof RecurrenceRule => (string) $value,
            default => throw InvalidType::new($value, self::NAME, ['null', RecurrenceRule::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RecurrenceRule
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return RecurrenceRule::parse($value);
        } catch (RecurrenceException $recurrenceException) {
            throw ValueNotConvertible::new($value, self::NAME, $recurrenceException->getMessage(), $recurrenceException);
        }
    }
}
