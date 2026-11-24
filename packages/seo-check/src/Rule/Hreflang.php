<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\RenderedPages;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\Address;
use Phil\SeoCheck\Markup\Alternate;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\Severity;
use Phil\Url\Url;

/**
 * The translations a page lists with `<link rel="alternate" hreflang="…">` are valid, and reciprocal: each names a
 * language as BCP 47 does, or `x-default`, at an absolute address; a language leads to one address; the page lists
 * itself, under its own language; and each translation lists the page back, without which search engines ignore the
 * pair. `x-default` is optional.
 *
 * A page that lists no translation passes. The translations are read through `RenderedPages`, `$maxPages` at most; a
 * translation the application does not render is not reported. Without `RenderedPages`, reciprocity is not checked.
 */
final readonly class Hreflang implements SeoRule
{
    public const string NAME = 'hreflang';

    /** The most translations read for reciprocity, by default. */
    public const int MAX_PAGES = 50;

    public function __construct(
        private ?RenderedPages $pages = null,
        private MarkupReader $reader = new MarkupReader(),
        private int $maxPages = self::MAX_PAGES,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        $primary = $page->primary();

        if ([] === $primary->markup->alternates) {
            return;
        }

        /** @var array<string, Url> $byLanguage */
        $byLanguage = [];
        $duplicates = [];
        $self = [];

        foreach ($primary->markup->alternates as $alternate) {
            $code = $alternate->hreflang;

            if ('x-default' !== strtolower($code) && !LanguageTag::isValid($code)) {
                yield new Finding(self::NAME, Severity::Error, new Message('hreflang.invalid-code', ['code' => Excerpt::of($code)]), Excerpt::of($alternate->href));

                continue;
            }

            if (!Address::isAbsolute($alternate->href)) {
                yield new Finding(self::NAME, Severity::Error, new Message('hreflang.relative', ['code' => $code, 'href' => Excerpt::of($alternate->href)]), Excerpt::of($alternate->href));

                continue;
            }

            $url = Address::resolve($alternate->href, $primary->page->url);

            if (!$url instanceof Url) {
                yield new Finding(self::NAME, Severity::Error, new Message('hreflang.invalid-url', ['code' => $code, 'href' => Excerpt::of($alternate->href)]), Excerpt::of($alternate->href));

                continue;
            }

            $key = LanguageTag::normalize($code);

            if (isset($byLanguage[$key]) && !$byLanguage[$key]->equals($url) && !isset($duplicates[$key])) {
                $duplicates[$key] = $code;

                yield new Finding(self::NAME, Severity::Error, new Message('hreflang.duplicate', ['code' => $code]), $code);
            }

            $byLanguage[$key] ??= $url;

            if ($url->equals($page->canonicalUrl)) {
                $self[] = $key;
            }
        }

        if ([] === $self) {
            yield new Finding(self::NAME, Severity::Warning, new Message('hreflang.missing-self', ['canonical' => $page->canonicalUrl->toString()]));
        } elseif (!\in_array(LanguageTag::normalize($page->locale), $self, true)) {
            yield new Finding(self::NAME, Severity::Warning, new Message('hreflang.self-language', ['codes' => implode(', ', $self), 'locale' => $page->locale]));
        }

        yield from $this->reciprocity($page, $byLanguage);
    }

    /**
     * @param array<string, Url> $byLanguage
     *
     * @return iterable<Finding>
     */
    private function reciprocity(CheckedPage $page, array $byLanguage): iterable
    {
        if (!$this->pages instanceof RenderedPages) {
            return;
        }

        $read = 0;

        foreach ($byLanguage as $code => $url) {
            if ('x-default' === $code || $url->equals($page->canonicalUrl)) {
                continue;
            }

            if (++$read > $this->maxPages) {
                return;
            }

            $translation = $this->pages->renderingAt($url);

            if ($translation instanceof RenderedPage && !$this->listsBack($this->reader->read($translation)->alternates, $translation->url, $page->canonicalUrl)) {
                yield new Finding(self::NAME, Severity::Warning, new Message('hreflang.not-reciprocal', ['code' => $code, 'href' => $url->toString()]), $url->toString());
            }
        }
    }

    /**
     * @param list<Alternate> $alternates
     */
    private function listsBack(array $alternates, Url $base, Url $canonical): bool
    {
        return array_any($alternates, static fn (Alternate $alternate): bool => Address::resolve($alternate->href, $base)?->equals($canonical) ?? false);
    }
}
