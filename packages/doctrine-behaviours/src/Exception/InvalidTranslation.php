<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use LogicException;

/**
 * A translation entity is not mapped as `Translations` reads it, or a record makes a translation that is not its own:
 * a fault of the code, found as the mapping is loaded or at the first read, never a value to show a visitor.
 */
final class InvalidTranslation extends LogicException
{
    public static function noLocale(string $class): self
    {
        return new self(\sprintf('The translation %s maps no field "locale": use TranslationEntity.', $class));
    }

    public static function noTranslatable(string $class): self
    {
        return new self(\sprintf('The translation %s maps no to-one relation "translatable" held by one join column.', $class));
    }

    public static function notATranslation(string $record, string $class): self
    {
        return new self(\sprintf('%s names %s as its translation, which is not an entity implementing Translation.', $record, $class));
    }

    public static function unknownField(string $record, string $class, string $field): self
    {
        return new self(\sprintf('%s names "%s" as a translated field, which %s does not map.', $record, $field, $class));
    }

    public static function foreign(string $record, string $locale): self
    {
        return new self(\sprintf('%s::newTranslation() made a translation of another record, class or locale than its own in "%s".', $record, $locale));
    }
}
