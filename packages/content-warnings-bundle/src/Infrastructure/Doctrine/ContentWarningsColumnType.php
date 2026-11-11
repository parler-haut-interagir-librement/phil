<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Exception\UnreadableWarnings;

/**
 * The column type `phil_content_warnings`: the `ContentWarnings` of any entity in one JSON column, as
 * `ContentWarnings::toArray()` writes them — `[{"theme":"gore","severity":"severe"},{"theme":"spiders"}]`.
 */
final class ContentWarningsColumnType extends Type
{
    public const string NAME = 'phil_content_warnings';

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
            $value instanceof ContentWarnings => json_encode($value->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw InvalidType::new($value, self::NAME, ['null', ContentWarnings::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ContentWarnings
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            $stored = json_decode($value, true, 3, \JSON_THROW_ON_ERROR);

            if (!\is_array($stored)) {
                throw UnreadableWarnings::because('the column holds no list');
            }

            return ContentWarnings::fromArray($stored);
        } catch (JsonException|UnreadableWarnings $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
