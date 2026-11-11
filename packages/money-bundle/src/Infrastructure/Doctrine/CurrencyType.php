<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\UnknownCurrency;

/**
 * `phil_currency`: a `Currency` in a fixed three-character column, its ISO 4217 code. A code read back that the
 * currency data no longer knows fails the hydration rather than passing unchecked.
 */
final class CurrencyType extends Type
{
    public const string NAME = 'phil_currency';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 3, 'fixed' => true] + $column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof Currency => $value->code,
            default => throw InvalidType::new($value, self::NAME, ['null', Currency::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Currency
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return Currency::of($value);
        } catch (UnknownCurrency $unknownCurrency) {
            throw ValueNotConvertible::new($value, self::NAME, $unknownCurrency->getMessage(), $unknownCurrency);
        }
    }
}
