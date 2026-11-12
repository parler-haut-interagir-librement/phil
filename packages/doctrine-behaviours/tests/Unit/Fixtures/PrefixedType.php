<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Override;

/**
 * A string stored behind a prefix: a value bound without its type finds no row, so the tests see each type given.
 */
final class PrefixedType extends Type
{
    public const string NAME = 'prefixed';

    public const string PREFIX = 'v:';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return \is_string($value) ? self::PREFIX.$value : null;
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return \is_string($value) ? substr($value, \strlen(self::PREFIX)) : null;
    }
}
