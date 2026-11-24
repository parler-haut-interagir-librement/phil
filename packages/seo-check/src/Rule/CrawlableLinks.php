<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\Address;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

/**
 * Search engines follow the links of a page by their `href`: a link with text but no `href`, or one that runs a
 * script, `javascript:…`, leads them nowhere. An `<a>` without `href` nor text is an anchor, not a link.
 */
final readonly class CrawlableLinks implements SeoRule
{
    public const string NAME = 'crawlable-links';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        foreach ($page->primary()->markup->links as $link) {
            if (null === $link->href) {
                if ('' !== $link->text) {
                    yield new Finding(self::NAME, Severity::Warning, new Message('crawlable-links.no-href', ['text' => Excerpt::of($link->text)]), Excerpt::of($link->text));
                }

                continue;
            }

            // A browser drops tabs and line breaks inside an address: `java\tscript:` runs too.
            if (str_starts_with(strtolower(str_replace(["\t", "\n", "\r"], '', Address::trim($link->href))), 'javascript:')) {
                yield new Finding(self::NAME, Severity::Warning, new Message('crawlable-links.javascript', ['href' => Excerpt::of($link->href), 'text' => Excerpt::of($link->text)]), Excerpt::of($link->href));
            }
        }
    }
}
