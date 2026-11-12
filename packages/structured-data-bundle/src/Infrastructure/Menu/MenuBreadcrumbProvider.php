<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Infrastructure\Menu;

use Override;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Schema;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\PageContext;

/**
 * The breadcrumb of a menu of phil/menu-bundle as the page's `BreadcrumbList`, `#breadcrumb` at the page's address:
 * its labels translated and its addresses absolute, as the menu's own JSON-LD gives them, built by `Schema`. The page's `WebPage` refers
 * to it when the configuration prints one. A page outside the menu has no breadcrumb.
 */
final readonly class MenuBreadcrumbProvider implements StructuredDataProvider
{
    public function __construct(
        private MenuExtension $menus,
        private string $menu,
        private bool $linkWebPage,
    ) {
    }

    #[Override]
    public function nodes(PageContext $page): iterable
    {
        $items = [];
        $elements = $this->menus->breadcrumbSchema($this->menu)['itemListElement'] ?? [];

        foreach (\is_array($elements) ? $elements : [] as $element) {
            if (\is_array($element) && \is_string($element['name'] ?? null)) {
                $items[] = ['name' => $element['name'], 'url' => \is_string($element['item'] ?? null) ? $element['item'] : null];
            }
        }

        if ([] === $items) {
            return [];
        }

        $id = $page->pageId('breadcrumb');
        $breadcrumb = Schema::breadcrumbList($items, $id);

        if (!$this->linkWebPage || null === $id) {
            return [$breadcrumb];
        }

        return [$breadcrumb, Node::of('WebPage', ['breadcrumb' => new Reference($id)], $page->pageId('webpage'))];
    }
}
