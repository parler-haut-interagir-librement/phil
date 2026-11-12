<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * `Translation`, mapped: the column `locale`, in the one spelling of `phil/locale-dimensions`. The entity maps the
 * to-one relation `translatable` to its record, by one join column, and its translated fields; `TranslationListener`
 * adds the unique index on the join column and the locale.
 *
 * @phpstan-require-implements Translation
 */
trait TranslationEntity
{
    #[ORM\Column(name: 'locale', type: Types::STRING, length: Translation::LOCALE_LENGTH)]
    protected string $locale;

    public function locale(): Locale
    {
        return Locale::fromString($this->locale);
    }

    /**
     * Sets the locale, once, from the constructor of the translation.
     */
    protected function writtenIn(Locale $locale): void
    {
        $this->locale = $locale->value;
    }
}
