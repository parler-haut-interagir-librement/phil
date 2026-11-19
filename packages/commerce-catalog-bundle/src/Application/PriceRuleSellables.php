<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells the core what the price rules of products sell:
 *
 * - `pay_what_you_want:<sku>`: the product at the price the buyer chooses, priced at zero with one required amount
 *   option, `price`, from its base price up to its maximum — its own, or the site's default; a product the buyer does
 *   not choose the price of, or without a maximum, sells nothing;
 * - `community_copy:<sku>`: one community copy of the product, funded at its copy price for someone who cannot pay.
 *
 * Both are described by the product's name in the buyer's locale, and taxed in its tax category. A withdrawn product
 * or a family sells neither. `reference()` and `configuration()` give what a line of a chosen price is added with.
 *
 * It holds no state between two calls.
 */
final readonly class PriceRuleSellables implements SellableResolver, SellableOptions
{
    public const string PAY_WHAT_YOU_WANT = 'pay_what_you_want';

    public const string COMMUNITY_COPY = 'community_copy';

    /** The code of the amount option that carries the price the buyer chose. */
    public const string PRICE_OPTION = 'price';

    /**
     * @param int|null $defaultMaximum the highest price a buyer may choose, in minor units of the product's currency,
     *                                 for a product without a maximum of its own
     */
    public function __construct(
        private ProductRepository $products,
        private ?ProductTexts $texts = null,
        private ?TranslatorInterface $translator = null,
        private ?int $defaultMaximum = null,
    ) {
    }

    /**
     * The reference of a line of the product at a price the buyer chooses, given by `configuration()`.
     */
    public static function reference(Product $product): SellableReference
    {
        return SellableReference::of(self::PAY_WHAT_YOU_WANT, $product->sku);
    }

    /**
     * The configuration of a line at the price the buyer chose; the price is checked by the option when the line is
     * added.
     */
    public static function configuration(Money $price): LineConfiguration
    {
        return LineConfiguration::of([self::PRICE_OPTION => OptionValue::chosen((string) $price->minorUnits)]);
    }

    /**
     * What one unit of a line of a chosen price costs before its promotions, in minor units of the order's currency: its
     * unit price and the price chosen, which its option checked when the line was added; `null` for a line of another
     * type.
     */
    public static function chosenPriceOf(OrderLine $line): ?int
    {
        $chosen = self::PAY_WHAT_YOU_WANT === $line->reference->type ? $line->configuration->value(self::PRICE_OPTION)?->value : null;

        return null === $chosen ? null : $line->unitPrice->minorUnits + (int) $chosen;
    }

    /**
     * The SKU a reference of either type sells, or `null` for another reference.
     */
    public static function skuOf(SellableReference $reference): ?string
    {
        return self::PAY_WHAT_YOU_WANT === $reference->type || self::COMMUNITY_COPY === $reference->type ? $reference->id : null;
    }

    #[Override]
    public function supports(string $type): bool
    {
        return self::PAY_WHAT_YOU_WANT === $type || self::COMMUNITY_COPY === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $product = $this->sold($reference->id);

        if (!$product instanceof Product) {
            return null;
        }

        if (self::COMMUNITY_COPY === $reference->type) {
            $copyPrice = $product->communityCopyPrice();

            if (!$copyPrice instanceof Money) {
                return null;
            }

            $name = $this->name($product);
            $description = $this->translator?->trans('phil_commerce_catalog.community_copy', ['%name%' => $name], 'phil_commerce_catalog') ?? 'Community copy of '.$name;

            return Sellable::of($reference, $description, $copyPrice, $product->taxCategory());
        }

        $range = $this->chosenPrices($product);

        return $range instanceof AmountRange
            ? Sellable::of($reference, $this->name($product), Money::zero($product->price->currency), $product->taxCategory(), $this->options($range))
            : null;
    }

    /**
     * The amount option of a chosen price, in the product's currency; a community copy has none.
     *
     * @throws CatalogRefused for an order in another currency than the product's, or a product whose buyer no longer
     *                        chooses the price, which would leave its line at zero
     */
    #[Override]
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        if (self::PAY_WHAT_YOU_WANT !== $reference->type) {
            return OptionSet::none();
        }

        $product = $this->sold($reference->id);
        $range = $product instanceof Product ? $this->chosenPrices($product) : null;

        if (!$range instanceof AmountRange) {
            throw CatalogRefused::noChosenPrice($reference->id);
        }

        if (!$range->minimum->currency->equals($currency)) {
            throw CatalogRefused::currency(\sprintf('The product %s', $reference->id), $range->minimum->currency->code, $currency->code);
        }

        return $this->options($range);
    }

    private function chosenPrices(Product $product): ?AmountRange
    {
        return $product->chosenPrices(null === $this->defaultMaximum ? null : Money::of($this->defaultMaximum, $product->price->currency));
    }

    private function options(AmountRange $range): OptionSet
    {
        $label = $this->translator?->trans('phil_commerce_catalog.chosen_price', [], 'phil_commerce_catalog') ?? 'Your price';

        return OptionSet::of(OptionDefinition::amount(self::PRICE_OPTION, $label, $range));
    }

    private function sold(string $sku): ?Product
    {
        $product = $this->products->find($sku);

        return $product instanceof Product && $product->isActive() && !$product->isFamily() ? $product : null;
    }

    private function name(Product $product): string
    {
        return $this->texts?->of([$product])->name($product) ?? $product->name();
    }
}
