<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Infrastructure\Twig;

use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Typographers;
use Stringable;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Attribute\AsTwigFilter;

/**
 * Typography in templates. `{{ page.body|typography }}` fixes HTML: a string that is not marked safe is escaped first,
 * so text from a visitor stays text, and HTML marked safe — `|raw`, the output of `ujo_html` — keeps its markup.
 * `{{ title|typography_text }}` fixes plain text, escaped as usual afterwards. Both take a locale,
 * `|typography('fr_CA')`; without one, they use the current locale of Symfony's `LocaleSwitcher` — the request's, or
 * the one `runWithLocale()` sets —, and the default locale of the typographers when there is no switcher. `null`
 * gives an empty string.
 */
final readonly class TypographyExtension
{
    /**
     * @param LocaleAwareInterface|null $current the current locale, Symfony's `LocaleSwitcher`; the default locale when null
     */
    public function __construct(
        private Typographers $typographers,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    /**
     * @throws TypographyException
     */
    #[AsTwigFilter(name: 'typography', isSafe: ['html'], preEscape: 'html')]
    public function html(string|Stringable|null $html, ?string $locale = null): string
    {
        return null === $html ? '' : $this->typographers->for($locale ?? $this->current?->getLocale())->fix(\is_string($html) ? $html : $html->__toString());
    }

    /**
     * @throws TypographyException
     */
    #[AsTwigFilter(name: 'typography_text')]
    public function text(string|Stringable|null $text, ?string $locale = null): string
    {
        return null === $text ? '' : $this->typographers->for($locale ?? $this->current?->getLocale())->fixText(\is_string($text) ? $text : $text->__toString());
    }
}
