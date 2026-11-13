<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Contract;

use Phil\Resource\ResourceReference;

/**
 * An object of the application whose access the bundle decides — an entity, a view model —, named by the reference
 * its tuples are written on.
 */
interface AccessibleItem
{
    public function accessReference(): ResourceReference;
}
