<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Phil\CommerceCatalogBundle\Application\Contract\ProductFamilies;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\Money\Money;

/**
 * The variants of a family: each one a product of its own, which gives each axis of its family one value, and no two
 * of a family with the same values. What a buyer chooses among is read from the variants still sold.
 */
final readonly class ProductVariants
{
    public function __construct(
        private ProductRepository $products,
        private ProductFamilies $families,
    ) {
    }

    /**
     * Creates and stores a variant of the family.
     *
     * @param array<string, string> $options axis → value
     */
    public function create(Product $family, string $sku, array $options, ?Money $price = null, ?string $name = null): Product
    {
        if ($this->products->find($sku) instanceof Product) {
            throw CatalogRefused::taken($sku);
        }

        $variant = Product::variant($family, $sku, $options, $price, $name);

        if ($this->find($family, $variant->options(), withdrawn: true) instanceof Product) {
            throw CatalogRefused::sameOptions($family->sku, $variant->options());
        }

        $this->products->add($variant);

        return $variant;
    }

    /**
     * @return list<Product> the variants of the family, withdrawn ones included, by SKU
     */
    public function variantsOf(Product $family): array
    {
        return $this->families->variantsOf($family->sku);
    }

    /**
     * The variant of the family with these values, whatever order they come in; a withdrawn one only when asked.
     *
     * @param array<string, string> $options axis → value
     */
    public function find(Product $family, array $options, bool $withdrawn = false): ?Product
    {
        ksort($options, \SORT_STRING);

        return array_find($this->variantsOf($family), static function (Product $variant) use ($options, $withdrawn): bool {
            $own = $variant->options();
            ksort($own, \SORT_STRING);

            return $own === $options && ($withdrawn || $variant->isActive());
        });
    }

    /**
     * The values a buyer chooses among, axis by axis in the family's order, each value once, in the order of the
     * variants' SKUs: those of the variants still sold.
     *
     * @return array<string, list<string>> axis → values
     */
    public function choices(Product $family): array
    {
        $choices = array_fill_keys($family->axes(), []);

        foreach ($this->variantsOf($family) as $variant) {
            if (!$variant->isActive()) {
                continue;
            }

            foreach ($variant->options() as $axis => $value) {
                if (isset($choices[$axis]) && !\in_array($value, $choices[$axis], true)) {
                    $choices[$axis][] = $value;
                }
            }
        }

        return $choices;
    }

    /**
     * The attributes a product shows: a variant's are its family's, with its own over them; another product's are its
     * own.
     *
     * @return array<string, string> attribute → value, by attribute
     */
    public function attributesOf(Product $product): array
    {
        $family = null === $product->family() ? null : $this->products->find($product->family());
        $attributes = [...$family?->attributes() ?? [], ...$product->attributes()];
        ksort($attributes, \SORT_STRING);

        return $attributes;
    }

    /**
     * Passes what a family decides for all its variants down to each of them: its tax category, its channels and
     * whether its units carry serial numbers. Names, prices, attributes and options stay each variant's own.
     *
     * @return list<Product> the variants changed, by SKU
     */
    public function cascade(Product $family): array
    {
        $changed = [];

        foreach ($this->variantsOf($family) as $variant) {
            if ($variant->taxCategory() === $family->taxCategory() && $variant->channels() === $family->channels() && $variant->tracksSerialNumbers() === $family->tracksSerialNumbers()) {
                continue;
            }

            $variant->classify($family->taxCategory());
            $variant->sellThrough($family->channels());
            $variant->trackSerialNumbers($family->tracksSerialNumbers());
            $changed[] = $variant;
        }

        return $changed;
    }

    /**
     * The lowest and the highest base price of the variants still sold, for a family shown with a range of prices;
     * `null` when none is sold.
     *
     * @return array{Money, Money}|null
     */
    public function priceRange(Product $family): ?array
    {
        $range = null;

        foreach ($this->variantsOf($family) as $variant) {
            if (!$variant->isActive()) {
                continue;
            }

            $price = $variant->price;
            $range = null === $range ? [$price, $price] : [$price->isLessThan($range[0]) ? $price : $range[0], $price->isGreaterThan($range[1]) ? $price : $range[1]];
        }

        return $range;
    }
}
