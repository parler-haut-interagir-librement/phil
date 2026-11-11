<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\OpeningHours\Exception\OpeningHoursException;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\OpeningHoursArray;

/**
 * The column type `phil_opening_hours`: `OpeningHours` in one JSON column, as `OpeningHoursArray` writes them.
 */
final class OpeningHoursColumnType extends Type
{
    public const string NAME = 'phil_opening_hours';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof OpeningHours => json_encode(OpeningHoursArray::from($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw InvalidType::new($value, self::NAME, ['null', OpeningHours::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?OpeningHours
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return OpeningHoursArray::to(json_decode($value, true, 6, \JSON_THROW_ON_ERROR));
        } catch (JsonException|OpeningHoursException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
