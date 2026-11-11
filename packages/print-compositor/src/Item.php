<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * Something drawn in a layer, inside its box: an image or a text.
 */
interface Item
{
    /**
     * Where the item is drawn, in millimetres from the top left corner of the zone.
     */
    public function box(): Box;
}
