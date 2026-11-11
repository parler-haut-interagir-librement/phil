<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The column type `phil_url`: a `Url` in a `VARCHAR(2048)`, written normalised.
 */
final class UrlColumnType extends Type
{
    public const string NAME = 'phil_url';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => Url::MAX_LENGTH] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof Url => $value->toString(),
            default => throw InvalidType::new($value, self::NAME, ['null', Url::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Url
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return Url::fromString($value);
        } catch (UrlException $urlException) {
            throw ValueNotConvertible::new($value, self::NAME, $urlException->getMessage(), $urlException);
        }
    }
}
