<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;

/**
 * A `Locale` in a string column, in its one spelling (`fr`, `fr_CA`).
 *
 * The column is a `VARCHAR` of the mapped length, twelve when none is mapped. A stored value that is not a
 * locale is refused on the way out as a DBAL conversion error: it says the column holds what no code of the
 * application wrote, and a row read past it would be found by no lookup.
 */
final class LocaleType extends Type
{
    public const string NAME = 'phil_content_locale';

    private const int DEFAULT_LENGTH = 12;

    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => $column['length'] ?? self::DEFAULT_LENGTH] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Locale) {
            throw InvalidType::new($value, self::class, ['null', Locale::class]);
        }

        return $value->value;
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Locale
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        try {
            return Locale::fromString($value);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw ValueNotConvertible::new($value, self::class, null, $invalidLocaleDimension);
        }
    }
}
