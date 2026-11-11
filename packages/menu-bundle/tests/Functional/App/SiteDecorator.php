<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Functional\App;

use Override;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\MenuDecorator;
use Phil\MenuBundle\Domain\MenuItem;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * What a site adds to its menu: counts on the games, a class on every item.
 */
#[AsTaggedItem(priority: 10)]
final class SiteDecorator implements MenuDecorator
{
    #[Override]
    public function supports(string $menu): bool
    {
        return 'main' === $menu;
    }

    #[Override]
    public function decorate(MenuItem $item, MenuContext $context): MenuItem
    {
        $item = $item->withClass('site');

        return 'games' === $item->key ? $item->withCount(120, 4) : $item;
    }
}
