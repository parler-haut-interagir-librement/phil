<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\Contact\ContactCard;
use Phil\Contact\ContactCardArray;
use Phil\Contact\Exception\ContactException;

/**
 * The column type `phil_contact`: a `ContactCard` in one JSON column, as `ContactCardArray` writes it.
 */
final class ContactCardColumnType extends Type
{
    public const string NAME = 'phil_contact';

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
            $value instanceof ContactCard => json_encode(ContactCardArray::from($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw InvalidType::new($value, self::NAME, ['null', ContactCard::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ContactCard
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return ContactCardArray::to(json_decode($value, true, 8, \JSON_THROW_ON_ERROR));
        } catch (JsonException|ContactException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
