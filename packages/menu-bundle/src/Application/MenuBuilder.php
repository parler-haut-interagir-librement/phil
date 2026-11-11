<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Application;

use Phil\MenuBundle\Domain\Menu;

/**
 * Makes a menu. A service implementing it is found by autoconfiguration; its `name()` is the menu's.
 */
interface MenuBuilder
{
    public function name(): string;

    public function build(MenuContext $context): Menu;
}
