<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Reading;

use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The sites a reader may read the counters of: **every site** for whoever runs the platform the sites share, or a
 * closed list of sites for a site's administrators. A site outside the scope is read as a site that does not exist,
 * so that its counters, and its very existence, stay hidden from the administrators of the other sites.
 *
 * The application builds a scope from its own access rules for each request; the scope holds no state of its own.
 */
final readonly class ReaderScope
{
    public const int MAX_SITES = 1_000;

    /**
     * @param array<string, SiteId>|null $sites by site reference, or `null` for every site
     */
    private function __construct(
        private ?array $sites,
    ) {
    }

    public static function everySite(): self
    {
        return new self(null);
    }

    /**
     * @throws InvalidValue when no site, or more than {@see MAX_SITES} distinct sites, are given
     */
    public static function ofSites(SiteId ...$sites): self
    {
        $list = [];

        foreach ($sites as $site) {
            $list[$site->value] = $site;
        }

        if ([] === $list || \count($list) > self::MAX_SITES) {
            throw InvalidValue::because(\sprintf('A reader scope names 1 to %d sites; %d were given.', self::MAX_SITES, \count($list)));
        }

        return new self($list);
    }

    public function mayRead(SiteId $site): bool
    {
        return null === $this->sites || isset($this->sites[$site->value]);
    }

    public function coversEverySite(): bool
    {
        return null === $this->sites;
    }
}
