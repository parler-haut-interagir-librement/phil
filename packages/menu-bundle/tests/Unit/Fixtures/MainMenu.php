<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Unit\Fixtures;

use Override;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;

/**
 * A main menu: a home page, and games with two children.
 */
final class MainMenu implements MenuBuilder
{
    #[Override]
    public function name(): string
    {
        return 'main';
    }

    #[Override]
    public function build(MenuContext $context): Menu
    {
        return Menu::of('main', [
            MenuItem::of('home', 'menu.home')->withRoute('home'),
            MenuItem::of('games', 'menu.games')->withRoute('games')->withIcon('icon-dice')->withDescription('Every game.')->withChildren(
                MenuItem::of('chess', 'menu.chess')->withRoute('game', ['slug' => 'chess']),
                MenuItem::of('elsewhere', 'menu.elsewhere')->withUrl('https://example.com/'),
            ),
        ], 'messages');
    }
}
