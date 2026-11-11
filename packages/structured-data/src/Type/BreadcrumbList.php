<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;

/**
 * A `BreadcrumbList`: the pages from the top of the site to the current one, each a `ListItem` at its position with
 * its name and address — the current page, last, may go without one.
 */
final class BreadcrumbList
{
    /**
     * @param list<array{name: string|LanguageString, url?: ?string}> $items from the top of the site to the current page
     */
    public static function of(array $items, ?string $id = null): Node
    {
        $elements = [];

        foreach ($items as $index => $item) {
            $elements[] = Node::of('ListItem', ['position' => $index + 1, 'name' => $item['name'], 'item' => $item['url'] ?? null]);
        }

        return Node::of('BreadcrumbList', ['itemListElement' => $elements], $id);
    }
}
