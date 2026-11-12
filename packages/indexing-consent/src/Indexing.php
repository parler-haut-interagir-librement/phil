<?php

declare(strict_types=1);

namespace Phil\IndexingConsent;

/**
 * How far a public record may travel beyond its own page, as the person it is about decides:
 *
 * - **listed**: everywhere — search engines, the sitemap, structured data, the site's own listings;
 * - **not indexed**: on the site and in its listings, but kept out of search engines: `noindex` for robots, left out of
 *   the sitemap and of structured data;
 * - **unlisted**: reachable only by its address: also left out of the site's own listings and search.
 *
 * A person who no longer wants their record found by their name withdraws it from search engines without removing it;
 * the site keeps the page for whoever has the link.
 */
enum Indexing: string
{
    case Listed = 'listed';
    case NotIndexed = 'not_indexed';
    case Unlisted = 'unlisted';

    /**
     * The strictest of the values: a record whose owner and whose page each decide is kept to the narrower.
     */
    public static function strictest(self $first, self ...$others): self
    {
        $strictest = $first;

        foreach ($others as $other) {
            if ($other->isStricterThan($strictest)) {
                $strictest = $other;
            }
        }

        return $strictest;
    }

    /**
     * The values a reader allows, from the widest: what a query keeps when it filters on the column of the choice
     * rather than asking each record.
     *
     * @return list<self>
     */
    public static function allowing(Reader $reader): array
    {
        return array_values(array_filter(self::cases(), static fn (self $indexing): bool => $indexing->allows($reader)));
    }

    public function allows(Reader $reader): bool
    {
        return match ($reader) {
            Reader::SearchEngines, Reader::Sitemap, Reader::StructuredData => self::Listed === $this,
            Reader::SiteListings => self::Unlisted !== $this,
        };
    }

    /**
     * The directives for robots — the `content` of `<meta name="robots">`, the value of `X-Robots-Tag` —, none when
     * the record is listed.
     *
     * @return list<string>
     */
    public function robotsDirectives(): array
    {
        return self::Listed === $this ? [] : ['noindex', 'noarchive'];
    }

    /**
     * The directives joined as a header or a meta tag takes them, or null when there are none.
     */
    public function robots(): ?string
    {
        return self::Listed === $this ? null : implode(', ', $this->robotsDirectives());
    }

    public function isStricterThan(self $other): bool
    {
        return match ($this) {
            self::Listed => false,
            self::NotIndexed => self::Listed === $other,
            self::Unlisted => self::Unlisted !== $other,
        };
    }
}
