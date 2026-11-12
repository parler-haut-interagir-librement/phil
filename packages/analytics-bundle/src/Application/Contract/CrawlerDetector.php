<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

/**
 * Whether a user agent declares itself a crawler.
 *
 * The adapter is the application's: an MIT-licensed crawler list such as `jaybizzle/crawler-detect` is the
 * intended source. Crawlers that do not declare themselves are left to the browser's robot verdict and to the
 * rate limiter.
 */
interface CrawlerDetector
{
    public function isDeclaredCrawler(string $userAgent): bool;
}
