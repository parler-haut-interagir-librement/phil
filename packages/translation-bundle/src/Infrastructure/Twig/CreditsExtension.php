<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Twig;

use Phil\TranslationBundle\Application\Credit;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\LanguageTag;
use Twig\Attribute\AsTwigFunction;

/**
 * The public credit of the people who translated a document, for the page that publishes it:
 *
 *     {% for credit in phil_translation_credits('article:42', 'fr-CA') %}…{% endfor %}
 *
 * or the bundle's own list, `{{ include('@PhilTranslation/credits.html.twig', {document: 'article:42', language: 'fr-CA'}) }}`.
 * A language that is not a tag gives no credit.
 */
final readonly class CreditsExtension
{
    public function __construct(
        private Translations $translations,
    ) {
    }

    /**
     * @return list<Credit>
     */
    #[AsTwigFunction(name: 'phil_translation_credits')]
    public function credits(string $document, string $language): array
    {
        try {
            return $this->translations->credits($document, LanguageTag::fromLocale($language));
        } catch (InvalidLanguage) {
            return [];
        }
    }
}
