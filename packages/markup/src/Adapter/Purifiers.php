<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

use Phil\Markup\HtmlPurifier;

/**
 * The purifier of the default policies that runs fastest where it runs: `AmmoniaPurifier` when the `ammonia` extension
 * is loaded, `SanitizerPurifier` otherwise. Both keep the same elements at each level; the choice changes the time,
 * not what a reader sees. Both refuse an HTML longer than their ceiling, 4 MiB unless configured.
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
     * @param int<1, max>       $maxBytes   the longest HTML purified, in bytes; a longer one is refused
     */
    public static function defaultPolicies(?array $mediaHosts = null, bool $extension = true, int $maxBytes = HtmlPurifier::DEFAULT_MAX_BYTES): HtmlPurifier
    {
        return $extension && AmmoniaPurifier::isAvailable()
            ? AmmoniaPurifier::defaultPolicies($mediaHosts, maxBytes: $maxBytes)
            : SanitizerPurifier::defaultPolicies($mediaHosts, $maxBytes);
    }
}
