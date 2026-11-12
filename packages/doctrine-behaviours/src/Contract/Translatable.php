<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\LocaleDimensions\Locale;

/**
 * A record whose text is written in several locales: its translated fields live on a translation entity of its own,
 * one row per locale, and the record keeps what every locale shares — a price, a date, an image. `Translations` reads
 * the row of the first locale of a fallback chain that has one, and creates the row of a locale on demand, empty: a
 * field is never copied from another locale.
 */
interface Translatable
{
    /**
     * The entity that holds the translated fields of this record, one row per locale.
     *
     * @return class-string<Translation>
     */
    public static function translationClass(): string;

    /**
     * The fields of the translation entity that hold text of this record, in the order a reader lists them.
     *
     * @return non-empty-list<string>
     */
    public static function translatedFields(): array;

    /**
     * A new, empty translation of this record in a locale; `Translations::translate()` persists it.
     */
    public function newTranslation(Locale $locale): Translation;
}
