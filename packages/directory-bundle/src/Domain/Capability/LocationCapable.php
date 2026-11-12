<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Capability;

use Phil\Geo\Coordinates;

/**
 * A row that holds where its place is on a map: the coordinates of `phil/geo`, shared by every locale.
 */
interface LocationCapable
{
    public function getLocation(): ?Coordinates;

    public function setLocation(?Coordinates $location): void;
}
