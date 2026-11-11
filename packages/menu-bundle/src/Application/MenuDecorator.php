<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Application;

use Phil\MenuBundle\Domain\MenuItem;

/**
 * Changes the items of the menus it supports: counts, classes, attributes, icons, children. A service implementing it
 * is found by autoconfiguration; decorators run in the order of their priority — `#[AsTaggedItem(priority: …)]`,
 * highest first —, each on every item, children before their parent.
 */
interface MenuDecorator
{
    public function supports(string $menu): bool;

    public function decorate(MenuItem $item, MenuContext $context): MenuItem;
}
