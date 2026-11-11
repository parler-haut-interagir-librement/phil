<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneNumber;

/**
 * The column type `phil_phone`: a `PhoneNumber` in a `VARCHAR(33)`, in E.164 with its extension as RFC 3966
 * writes it, `+15145550123;ext=12`.
 */
final class PhoneNumberColumnType extends Type
{
    public const string NAME = 'phil_phone';

    /**
     * The longest number `PhoneNumber::parse()` accepts, written: `+`, a country code of three digits, a national
     * number of seventeen, `;ext=` and an extension of seven.
     */
    public const int LENGTH = 1 + 3 + 17 + 5 + 7;

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => self::LENGTH] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof PhoneNumber => (string) $value,
            default => throw InvalidType::new($value, self::NAME, ['null', PhoneNumber::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return PhoneNumber::parse($value);
        } catch (PhoneException $phoneException) {
            throw ValueNotConvertible::new($value, self::NAME, $phoneException->getMessage(), $phoneException);
        }
    }
}
