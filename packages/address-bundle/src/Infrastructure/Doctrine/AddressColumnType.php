<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\Address\Address;
use Phil\Address\AddressArray;
use Phil\Address\Exception\AddressException;

/**
 * The column type `phil_address`: an `Address` in one JSON column, as `AddressArray` writes it.
 */
final class AddressColumnType extends Type
{
    public const string NAME = 'phil_address';

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
            $value instanceof Address => json_encode(AddressArray::from($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw InvalidType::new($value, self::NAME, ['null', Address::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Address
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return AddressArray::to(json_decode($value, true, 3, \JSON_THROW_ON_ERROR));
        } catch (JsonException|AddressException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
