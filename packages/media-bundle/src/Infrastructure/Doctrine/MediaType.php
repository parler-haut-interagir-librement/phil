<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * The column type `phil_media`: a medium chosen for a record — a cover, a portrait, a logo — kept as its identifier in
 * a fixed 36-character column and read back as a `MediaId`. A `Media` is accepted when written.
 *
 * The column has no foreign key, so the bundle itself applies what a deletion does to it: see `DeleteBehaviour`.
 */
final class MediaType extends Type
{
    public const string NAME = 'phil_media';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            $value instanceof MediaId => $value->value,
            $value instanceof Media => $value->id,
            default => throw InvalidType::new($value, self::NAME, ['null', MediaId::class, Media::class]),
        };
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MediaId
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        try {
            return MediaId::fromString(trim($value));
        } catch (InvalidMedia $invalidMedia) {
            throw ValueNotConvertible::new($value, self::NAME, $invalidMedia->getMessage(), $invalidMedia);
        }
    }
}
