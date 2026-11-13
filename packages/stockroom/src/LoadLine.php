<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * One line of a load list: so many pieces of an item, taken from a container, or from wherever the item is when none
 * is named.
 */
final readonly class LoadLine
{
    /**
     * @throws InvalidStock when the item or the container is not an identifier, or the quantity is out of bounds
     */
    public function __construct(
        public string $item,
        public int $quantity = 1,
        public ?string $from = null,
    ) {
        Identifier::check($item, 'item of a load line');
        Quantity::check($quantity);
        Identifier::optional($from, 'container of a load line');
    }
}
