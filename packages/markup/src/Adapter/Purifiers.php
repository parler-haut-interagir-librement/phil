<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Phil\Markup\HtmlPurifier;

/**
 * The purifier of the default policies that runs fastest where it runs: `AmmoniaPurifier` when the `ammonia` extension
 * is loaded, `SanitizerPurifier` otherwise. Both keep the same elements at each level; the choice changes the time,
 * not what a reader sees.
 */
final readonly class Purifiers
{
    private function __construct()
    {
    }

    /**
     * @param list<string>|null $mediaHosts the hosts images may come from, besides the site itself; `null` for any host,
     *                                      `[]` for none
     * @param bool              $extension  whether to use the extension when it is loaded; `false` keeps the sanitizer
     */
    public static function defaultPolicies(?array $mediaHosts = null, bool $extension = true): HtmlPurifier
    {
        return $extension && AmmoniaPurifier::isAvailable()
            ? AmmoniaPurifier::defaultPolicies($mediaHosts)
            : SanitizerPurifier::defaultPolicies($mediaHosts);
    }
}
