<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\ContentBundle\Domain\Dimension\Stage;

/**
 * A `Stage` in a string column, by its backing value (`draft`, `live`).
 *
 * A plain `VARCHAR` rather than a database enum: MySQL and PostgreSQL spell enums differently, and adding a
 * stage would then be a schema change on one and a type change on the other.
 */
final class StageType extends Type
{
    public const string NAME = 'phil_content_stage';

    private const int DEFAULT_LENGTH = 8;

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

        // The ORM unwraps a backed enum before it reaches the type, in `findBy()` criteria as in DQL
        // parameters: a stage arrives as its backing value as often as a `Stage`. Anything else is refused.
        $stage = \is_string($value) ? Stage::tryFrom($value) : $value;

        if (!$stage instanceof Stage) {
            throw InvalidType::new($value, self::class, ['null', Stage::class, 'the value of a '.Stage::class]);
        }

        return $stage->value;
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Stage
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        return Stage::tryFrom($value) ?? throw ValueNotConvertible::new($value, self::class);
    }
}
