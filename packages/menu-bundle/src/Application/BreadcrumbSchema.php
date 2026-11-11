<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Application;

use Closure;
use Phil\MenuBundle\Domain\MenuItem;

/**
 * A breadcrumb as schema.org reads it, a `BreadcrumbList`: each item at its position, with its name and, when it
 * has one, its URL — the current page, last, may have none.
 *
 * The name and the URL are what a visitor reads and follows, not what the item holds: a label that is a translation
 * key is translated, and a route becomes an absolute URL. The caller gives both, as `MenuExtension` does with the
 * translator and the router; without them, the label is the name and the item's own address the URL.
 */
final class BreadcrumbSchema
{
    /**
     * @param list<MenuItem>                    $items from the root to the current page
     * @param (Closure(MenuItem): string)|null  $name  the name shown for an item
     * @param (Closure(MenuItem): ?string)|null $url   the absolute URL of an item, or `null` for none
     *
     * @return array<string, mixed>
     */
    public static function of(array $items, ?Closure $name = null, ?Closure $url = null): array
    {
        $name ??= static fn (MenuItem $item): string => $item->label;
        $url ??= static fn (MenuItem $item): ?string => $item->url;
        $elements = [];

        foreach ($items as $index => $item) {
            $element = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $name($item)];
            $address = $url($item);

            $elements[] = null === $address ? $element : [...$element, 'item' => $address];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elements];
    }
}
