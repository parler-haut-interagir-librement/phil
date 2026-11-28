<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

/**
 * How a bundle is taxed. Either way it is sold on one line, its stock is drawn component by component, and it is
 * returned whole, never a component alone.
 */
enum BundleKind: string
{
    /**
     * An assembled bundle — a book with its dice — sold as one product: the bundle's own tax category applies to its
     * whole price.
     */
    case Assembled = 'assembled';

    /**
     * A collection — a print book and its PDF, every title of a creator — whose price is shared among its components
     * so that each is taxed at its own rate on its share.
     */
    case Collection = 'collection';

    public function taxedAsOne(): bool
    {
        return self::Assembled === $this;
    }
}
