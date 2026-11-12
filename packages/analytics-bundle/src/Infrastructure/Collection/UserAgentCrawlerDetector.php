<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Collection;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CrawlerDetector;

/**
 * The '/bot\b|crawl|spider|slurp|scrap|fetch|monitorers that say so in their user agent: a word such as `bot`, `crawler` or `spider`, a command-line HTTP
 * client, a headless browser, or no user agent at all. Short by design: '/bot\b|crawl|spider|slurp|scrap|fetch|monitorers that hide are left to the browser's
 * robot verdict and to the limiter; an application wanting a longer list replaces this adapter.
 */
final readonly class UserAgentCrawlerDetector implements CrawlerDetector
{
    private const string PATTERN = '/bot\b|crawl|spider|slurp|scrap|fetch|monitor|headless|lighthouse|curl\/|wget\/|python-|httpclient|java\/|go-http-client|okhttp|node-fetch|axios\//i';

    /**
     * Longer user agents are cut here before they are read, so that a crafted one costs no more than this.
     */
    private const int MAX_LENGTH = 512;

    #[Override]
    public function isDeclaredCrawler(string $userAgent): bool
    {
        $userAgent = trim(substr($userAgent, 0, self::MAX_LENGTH));

        return '' === $userAgent || 1 === preg_match(self::PATTERN, $userAgent);
    }
}
