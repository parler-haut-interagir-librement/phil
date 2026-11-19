<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\CommerceCatalogBundle\Domain\Model\Product;

/**
 * Where products are filed beyond the catalogue: the categories of a tree and the tags shared with the rest of the
 * site, renamed and merged in one place. `Infrastructure\Taxonomy\TaxonomyProductClassification` files them in
 * `phil/taxonomy-bundle`.
 */
interface ProductClassification
{
    /**
     * Gives the product exactly these tags; a name no tag can be made of is left out.
     *
     * @param list<string> $names
     *
     * @return list<string> the names of the tags it now has
     */
    public function tag(Product $product, array $names): array;

    /**
     * @return list<string> the names of the tags the product has
     */
    public function tagsOf(Product $product): array;

    /**
     * Files the product in exactly these categories, by their keys; a key no category has is refused.
     *
     * @param list<string> $keys
     */
    public function categorize(Product $product, array $keys): void;

    /**
     * @return list<string> the keys of the categories the product is filed in
     */
    public function categoriesOf(Product $product): array;

    /**
     * @return list<string> the SKUs of the products filed in the category, its whole branch included
     */
    public function inCategory(string $key): array;

    /**
     * Takes every tag and category away from the product.
     */
    public function forget(Product $product): void;
}
