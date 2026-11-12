<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Functional\App;

use Override;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;

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
        return Menu::of('main', [MenuItem::of('games', 'Jeux')->withUrl('/jeux')->withChildren(MenuItem::of('event', 'Tournoi')->withRoute('event'))]);
    }
}
