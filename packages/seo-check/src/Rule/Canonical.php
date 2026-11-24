<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\Address;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Rendering;
use Phil\SeoCheck\Severity;
use Phil\Url\Url;

/**
 * The page names one canonical address, absolute, and it is the address the page was published at: search engines
 * gather what they know of a page under its canonical address, and index the address it names rather than the one they
 * read.
 *
 * Every other path that leads to the page — `/` and `/fr/` for a home page — names the same canonical address: a path
 * that names another, or none, splits the page in two for search engines. A rendering that answers with a redirect or
 * an error is left to `http-status`.
 */
final readonly class Canonical implements SeoRule
{
    public const string NAME = 'canonical';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        foreach ($page->renderings as $index => $rendering) {
            $status = $rendering->page->status;

            if ($status >= 200 && $status < 300) {
                yield from $this->rendering($rendering, $page->canonicalUrl, 0 === $index);
            }
        }
    }

    /**
     * @return iterable<Finding>
     */
    private function rendering(Rendering $rendering, Url $expected, bool $primary): iterable
    {
        $path = Excerpt::path($rendering->page->url);
        $hrefs = $rendering->markup->canonicals;

        if ([] === $hrefs) {
            yield $primary
                ? new Finding(self::NAME, Severity::Error, new Message('canonical.missing', ['expected' => $expected->toString()]), $path)
                : new Finding(self::NAME, Severity::Error, new Message('canonical.variant-missing', ['path' => $path, 'expected' => $expected->toString()]), $path);
        }

        $found = [];

        foreach ($hrefs as $href) {
            if (!Address::isAbsolute($href)) {
                yield new Finding(self::NAME, Severity::Warning, new Message('canonical.relative', ['href' => Excerpt::of($href)]), $path);
            }

            $url = Address::resolve($href, $rendering->page->url);

            if (!$url instanceof Url) {
                yield new Finding(self::NAME, Severity::Error, new Message('canonical.invalid', ['href' => Excerpt::of($href)]), $path);

                continue;
            }

            $found[$url->toString()] = $url;
        }

        if (\count($found) > 1) {
            yield new Finding(self::NAME, Severity::Error, new Message('canonical.conflicting', ['count' => \count($found), 'path' => $path]), $path);

            return;
        }

        foreach ($found as $url) {
            if (!$url->equals($expected)) {
                yield $primary
                    ? new Finding(self::NAME, Severity::Error, new Message('canonical.mismatch', ['found' => $url->toString(), 'expected' => $expected->toString()]), $path)
                    : new Finding(self::NAME, Severity::Error, new Message('canonical.variant-mismatch', ['path' => $path, 'found' => $url->toString(), 'expected' => $expected->toString()]), $path);
            }
        }
    }
}
