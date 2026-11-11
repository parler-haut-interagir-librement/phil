<?php

declare(strict_types=1);

namespace Phil\Url;

use Phil\Enums\Collection\EnumSet;
use Phil\Url\Exception\InvalidUrl;

/**
 * Which kind an address is, among the kinds a field accepts: the first case, set by set and in each enum's
 * declaration order, whose rule accepts it.
 *
 * A field takes one set or several — the international kinds and the application's own —, so that an address on
 * Facebook is a Facebook page wherever it is entered, and one on no known domain falls to the website, when the
 * field accepts websites.
 */
final class UrlTypes
{
    /**
     * @param EnumSet<covariant UrlType> ...$accepted
     */
    public static function detect(Url $url, EnumSet ...$accepted): ?UrlType
    {
        foreach ($accepted as $set) {
            foreach ($set as $type) {
                if ($type->accepts($url)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * The address with its kind, or `InvalidUrl` when no accepted kind takes it.
     *
     * @param EnumSet<covariant UrlType> ...$accepted
     */
    public static function type(Url|string $url, EnumSet ...$accepted): TypedUrl
    {
        $url = $url instanceof Url ? $url : Url::fromString($url);
        $type = self::detect($url, ...$accepted) ?? throw InvalidUrl::notOfType($url->toString(), implode(', ', array_map(static fn (EnumSet $set): string => implode(', ', $set->names()), $accepted)));

        return TypedUrl::of($type, $url);
    }
}
