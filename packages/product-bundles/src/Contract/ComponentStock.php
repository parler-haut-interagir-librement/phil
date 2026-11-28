<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Contract;

/**
 * The units of a component that can be sold now: on hand less reserved, at the locations the shop sells from.
 */
interface ComponentStock
{
    /**
     * The units available; zero or less when none is.
     */
    public function available(string $sku): int;
}
