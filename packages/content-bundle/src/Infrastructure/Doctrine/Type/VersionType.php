<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Version;

/**
 * A `Version` in an integer column.
 *
 * A driver may hand an integer column back as a numeric string; both are read. A stored zero or negative
 * number is refused as a conversion error, for the reason `Version::of()` refuses it.
 */
final class VersionType extends Type
{
    public const string NAME = 'phil_content_version';

    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    #[Override]
    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Version) {
            throw InvalidType::new($value, self::class, ['null', Version::class]);
        }

        return $value->number;
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Version
    {
        if (null === $value) {
            return null;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d+$/D', $value)) {
            $value = (int) $value;
        }

        if (!\is_int($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        try {
            return Version::of($value);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw ValueNotConvertible::new((string) $value, self::class, null, $invalidLocaleDimension);
        }
    }
}
