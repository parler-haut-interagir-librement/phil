<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;

/**
 * `phil_measurement`: a `Measurement` in one string column, its exact decimal and its unit code, `12.5 kg`, never a
 * float. The column holds 100 characters unless the mapping gives its `length`.
 *
 * The text is read back by the registry of the application, so that its own units are known: a unit the registry no
 * longer holds, or a text that is not a measurement, fails the hydration rather than passing unchecked. A column to
 * sort or sum in SQL is mapped as two columns instead, a `decimal` and a unit code: see the bundle's Doctrine guide.
 */
final class MeasurementType extends Type
{
    public const string NAME = 'phil_measurement';

    /** The length of the column when the mapping gives none: a canonical decimal, a space and a code of 64 bytes. */
    public const int LENGTH = 100;

    public function __construct(
        private readonly UnitRegistry $registry,
    ) {
    }

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => $column['length'] ?? self::LENGTH] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof Measurement => $value->toString(),
            default => throw InvalidType::new($value, self::NAME, ['null', Measurement::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Measurement
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return $this->registry->parse($value);
        } catch (MeasurementException $measurementException) {
            throw ValueNotConvertible::new($value, self::NAME, $measurementException->getMessage(), $measurementException);
        }
    }
}
