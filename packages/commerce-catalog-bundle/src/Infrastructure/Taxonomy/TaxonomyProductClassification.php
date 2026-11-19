<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Taxonomy;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\ProductClassification;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Exception\InvalidTaxonomy;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;

/**
 * The categories and tags of `phil/taxonomy-bundle`: a product is the resource `phil_commerce_product`, by its SKU.
 */
final readonly class TaxonomyProductClassification implements ProductClassification
{
    public const string RESOURCE = 'phil_commerce_product';

    /** The taxonomy's service. */
    public const string TAXONOMY = Taxonomy::class;

    public function __construct(
        private Taxonomy $taxonomy,
        private TaxonomyRepository $repository,
    ) {
    }

    #[Override]
    public function tag(Product $product, array $names): array
    {
        $kept = [];

        foreach ($names as $name) {
            try {
                $kept[] = $this->taxonomy->tag($name)->name();
            } catch (InvalidTaxonomy) {
                continue;
            }
        }

        return array_map(static fn (Tag $tag): string => $tag->name(), $this->taxonomy->setTags($this->resource($product), $kept));
    }

    #[Override]
    public function tagsOf(Product $product): array
    {
        return array_map(static fn (Tag $tag): string => $tag->name(), $this->taxonomy->tagsOf($this->resource($product)));
    }

    #[Override]
    public function categorize(Product $product, array $keys): void
    {
        $this->taxonomy->categorize($this->resource($product), array_map($this->category(...), $keys));
    }

    #[Override]
    public function categoriesOf(Product $product): array
    {
        return array_map(static fn (Category $category): string => $category->key(), $this->taxonomy->categoriesOf($this->resource($product)));
    }

    #[Override]
    public function inCategory(string $key): array
    {
        return array_map(static fn (ResourceReference $resource): string => $resource->id, $this->taxonomy->categorizedIn($this->category($key), resourceKind: self::RESOURCE));
    }

    #[Override]
    public function forget(Product $product): void
    {
        $this->taxonomy->setTags($this->resource($product), []);
        $this->taxonomy->categorize($this->resource($product), []);
    }

    private function category(string $key): Category
    {
        return $this->repository->categoryByKey($key) ?? throw CatalogRefused::unknownCategory($key);
    }

    private function resource(Product $product): ResourceReference
    {
        return ResourceReference::of(self::RESOURCE, $product->sku);
    }
}
