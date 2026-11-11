<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\Typography;

use Override;
use Phil\Markup\Level;
use Phil\Markup\LevelAwareCompiler;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;
use Phil\Typography\Typographers;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Smart punctuation in every format: the HTML of the compiler it decorates, run through the typographer of a locale —
 * typographic quotes, dashes, ellipses, the spaces the locale puts around punctuation. It rewrites text only, never
 * inside `pre`, `code` and the other protected elements, and the purifier still runs after it, so the HTML is as safe
 * as without it. A format another pass already fixes, such as those `phil/typography-bundle` fixes through the UJO
 * renderer, is left as it is.
 */
final readonly class SmartPunctuationCompiler implements LevelAwareCompiler
{
    /**
     * @param list<string>              $formats the names of the formats fixed
     * @param string|null               $locale  the locale of the fixes; the current one when null
     * @param LocaleAwareInterface|null $current the current locale, Symfony's `LocaleSwitcher`; the default locale of the typographers when null
     */
    public function __construct(
        private MarkupCompiler $compiler,
        private Typographers $typographers,
        private array $formats = [],
        private ?string $locale = null,
        private ?LocaleAwareInterface $current = null,
    ) {
    }

    #[Override]
    public function compile(string $source, MarkupFormat|string $format): string
    {
        return $this->fix($this->compiler->compile($source, $format), $format);
    }

    #[Override]
    public function compileFor(string $source, MarkupFormat|string $format, Level $level): string
    {
        $html = $this->compiler instanceof LevelAwareCompiler ? $this->compiler->compileFor($source, $format, $level) : $this->compiler->compile($source, $format);

        return $this->fix($html, $format);
    }

    private function fix(string $html, MarkupFormat|string $format): string
    {
        return \in_array(MarkupFormat::nameOf($format), $this->formats, true) ? $this->typographers->for($this->locale ?? $this->current?->getLocale())->fix($html) : $html;
    }
}
