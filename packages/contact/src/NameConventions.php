<?php

declare(strict_types=1);

namespace Phil\Contact;

use Locale;

/**
 * The naming conventions the library knows, by the language of a name and, for Dutch, its country.
 *
 * | Culture | Order | Sort key |
 * |---|---|---|
 * | Chinese, Japanese, Korean | Family name first; native characters joined without a space | Family name |
 * | Hungarian | Family name first | Family name |
 * | Vietnamese | Family name first | Given name |
 * | Icelandic | Given name first | Given name |
 * | Dutch of the Netherlands, German, Portuguese | Given name first | Family name without its particle |
 * | French | Given name first | Family name without a leading `de` or `d'` |
 * | Spanish | Given name first | Family name without a leading `de` |
 * | Any other, Dutch of Belgium included | Given name first | Family name with its particle |
 */
final class NameConventions
{
    /**
     * @param ?string $culture a BCP 47 language tag, `nl-BE`; none or one the library does not know gives the convention
     *                         of the other cultures
     */
    public static function for(?string $culture): NameConvention
    {
        $language = null === $culture ? '' : Locale::getPrimaryLanguage($culture);

        return match ($language) {
            'zh', 'ja', 'ko' => new NameConvention(NameOrder::FamilyFirst, joinsNativeScript: true),
            'hu' => new NameConvention(NameOrder::FamilyFirst),
            'vi' => new NameConvention(NameOrder::FamilyFirst, sortsByGiven: true),
            'is' => new NameConvention(sortsByGiven: true),
            'nl' => new NameConvention(dropsWholeParticle: 'BE' !== Locale::getRegion($culture)),
            'de', 'pt' => new NameConvention(dropsWholeParticle: true),
            'fr' => new NameConvention(droppedParticles: ['de', "d'", 'd’']),
            'es' => new NameConvention(droppedParticles: ['de']),
            default => new NameConvention(),
        };
    }
}
