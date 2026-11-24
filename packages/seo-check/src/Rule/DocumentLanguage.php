<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

/**
 * The `lang` of the document is the language the page was published in: search engines show a page to readers of its
 * language, and screen readers read it with that language's voice. A `Content-Language` header, when the response
 * sends one, names that language too.
 */
final readonly class DocumentLanguage implements SeoRule
{
    public const string NAME = 'document-language';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $primary = $page->primary();
        $lang = $primary->markup->language;

        if (null === $lang || '' === $lang) {
            yield new Finding(self::NAME, Severity::Error, new Message('document-language.missing', ['locale' => $page->locale]));
        } elseif (LanguageTag::normalize($lang) !== LanguageTag::normalize($page->locale)) {
            yield LanguageTag::primary($lang) === LanguageTag::primary($page->locale)
                ? new Finding(self::NAME, Severity::Warning, new Message('document-language.region', ['lang' => Excerpt::of($lang), 'locale' => $page->locale]))
                : new Finding(self::NAME, Severity::Error, new Message('document-language.mismatch', ['lang' => Excerpt::of($lang), 'locale' => $page->locale]));
        }

        $header = implode(', ', $primary->page->header('Content-Language'));

        if ('' !== $header && !$this->names($header, $page->locale)) {
            yield new Finding(self::NAME, Severity::Warning, new Message('document-language.header', ['header' => Excerpt::of($header), 'locale' => $page->locale]));
        }
    }

    /**
     * Whether one of the languages of a `Content-Language` header is the page's, or its primary language.
     */
    private function names(string $header, string $locale): bool
    {
        return array_any(explode(',', $header), static fn (string $tag): bool => \in_array(LanguageTag::normalize($tag), [LanguageTag::normalize($locale), LanguageTag::primary($locale)], true));
    }
}
