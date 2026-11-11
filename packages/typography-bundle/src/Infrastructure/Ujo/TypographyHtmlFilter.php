<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Infrastructure\Ujo;

use Override;
use Phil\Typography\Typographers;
use Phil\Ujo\Html\HtmlFilter;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The typographer of a locale, run on the HTML of each UJO document: it rewrites text only, so the HTML stays as
 * purified as `phil/ujo` rendered it. Without a locale of its own, it follows the current locale of Symfony's
 * `LocaleSwitcher`, then the default locale of the typographers.
 */
final readonly class TypographyHtmlFilter implements HtmlFilter
{
    /**
     * @param string|null               $locale  the locale of the fixes; the current one when null
     * @param LocaleAwareInterface|null $current the current locale, Symfony's `LocaleSwitcher`; the default one when null
     */
    public function __construct(
        private Typographers $typographers,
        private ?string $locale = null,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    #[Override]
    public function filter(string $html): string
    {
        return $this->typographers->for($this->locale ?? $this->current?->getLocale())->fix($html);
    }
}
