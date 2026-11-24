<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Rule;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\LinkStatuses;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Markup\Address;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;
use Phil\Url\Url;

/**
 * The links of the page lead somewhere: a link that answers with an error wastes the visit of a search engine and the
 * patience of a reader. The statuses come from the application's `LinkStatuses`, as a link checker last saw them; the
 * check requests nothing.
 *
 * Each address is reported once, its fragment dropped: an error of the client, `404` or `410`, is an error; an error of
 * the server may pass, and is a warning; a redirect is an information, a link to correct when convenient. At most
 * `$maxLinks` addresses are asked about.
 */
final readonly class BrokenLinks implements SeoRule
{
    public const string NAME = 'broken-links';

    /** The most addresses asked about, by default. */
    public const int MAX_LINKS = 1_000;

    public function __construct(
        private LinkStatuses $statuses,
        private int $maxLinks = self::MAX_LINKS,
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
        /** @var array<string, Url> $links */
        $links = [];

        foreach ($primary->markup->links as $link) {
            $url = null === $link->href ? null : Address::resolve($link->href, $primary->page->url, keepFragment: false);

            if ($url instanceof Url && \count($links) < $this->maxLinks) {
                $links[$url->toString()] = $url;
            }
        }

        if ([] === $links) {
            return;
        }

        $statuses = $this->statuses->statuses(array_values($links));

        foreach (array_keys($links) as $href) {
            $status = $statuses[$href] ?? null;

            if (null === $status || $status < 300) {
                continue;
            }

            $parameters = ['href' => $href, 'status' => $status];

            yield match (true) {
                $status >= 500 => new Finding(self::NAME, Severity::Warning, new Message('broken-links.server-error', $parameters), $href),
                $status >= 400 => new Finding(self::NAME, Severity::Error, new Message('broken-links.broken', $parameters), $href),
                default => new Finding(self::NAME, Severity::Info, new Message('broken-links.redirect', $parameters), $href),
            };
        }
    }
}
