<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;

/**
 * A SKU the shop keeps stock of, with the name and the category the stock reports show for it.
 */
final readonly class StockedSku
{
    /**
     * @throws InvalidFigure when a text is empty or longer than 200 characters
     */
    public function __construct(
        public string $sku,
        public string $product,
        public ?string $category = null,
    ) {
        Text::required('SKU', $sku);
        Text::required('product', $product);
        Text::optional('category', $category);
    }
}
