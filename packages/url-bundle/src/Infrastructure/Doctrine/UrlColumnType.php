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

    /** Whether `localhost` and the names under `.localhost` are read back, as `phil_url.allow_localhost` says. */
    private static bool $allowLocalhost = false;

    /**
     * Doctrine builds a column type by its class and keeps one: the bundle sets this switch when it boots, from
     * `phil_url.allow_localhost`.
     */
    public static function allowLocalhost(bool $allow): void
    {
        self::$allowLocalhost = $allow;
    }

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
            return Url::fromString($value, allowLocalhost: self::$allowLocalhost);
        } catch (UrlException $urlException) {
            throw ValueNotConvertible::new($value, self::NAME, $urlException->getMessage(), $urlException);
        }
    }
}
