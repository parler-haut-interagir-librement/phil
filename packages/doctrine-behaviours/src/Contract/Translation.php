<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\LocaleDimensions\Locale;

/**
 * One locale of a `Translatable` record: the translated fields, the record they belong to, and the locale they are
 * written in. There is one row per record and locale, which `TranslationListener` keeps with a unique index; the
 * record and the locale of a row never change once it is stored.
 *
 * A translation that changes over time takes `Versionable` as any record does: each locale then has its own versions,
 * frozen and forked on its own.
 */
interface Translation
{
    public const string LOCALE_FIELD = 'locale';

    public const string TRANSLATABLE_FIELD = 'translatable';

    /**
     * The longest locale stored: a language of three letters and a region of two, with room to spare.
     */
    public const int LOCALE_LENGTH = 12;

    public function translatable(): Translatable;

    public function locale(): Locale;
}
