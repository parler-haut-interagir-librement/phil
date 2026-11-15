<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Money\Money;
use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\Url\Url;

/**
 * One product or variant a feed publishes, in the fields of the product data specification comparison sites and
 * marketplaces read.
 *
 * Every field is checked when the item is made, so that a feed file never holds an item a reader refuses for its form:
 *
 * - **Text** is UTF-8, cleaned of the control characters XML cannot hold, trimmed, on one line but for the
 *   description, and no longer than its field allows: 150 characters for the title, 5 000 for the description.
 * - **Identifiers** — the item's, its group's — are 1 to 50 visible ASCII characters; the GTIN is 8, 12, 13 or 14
 *   digits whose last one checks the others.
 * - **Addresses** are `phil/url` values: absolute, HTTP or HTTPS, in their canonical form; ten additional images at
 *   most.
 * - **Prices** are `phil/money` values, never negative. A sale price is in the price's currency and below it; a sale
 *   period comes only with a sale price.
 * - **An availability date** comes only with a preorder or a backorder.
 * - **Shipping** is a hundred costs at most, one per country and service; the shipping weight is 1 gram to a tonne,
 *   in grams; a multipack holds 2 to 10 000 units of one product; five custom labels at most.
 *
 * Variants of one product share its `itemGroupId` and differ by their colour, size, material or pattern; clothing
 * adds the age group and the gender it is made for.
 */
final readonly class FeedItem
{
    public const int MAX_ID = 50;

    public const int MAX_TITLE = 150;

    public const int MAX_DESCRIPTION = 5_000;

    public const int MAX_ADDITIONAL_IMAGES = 10;

    public const int MAX_BRAND = 70;

    public const int MAX_MPN = 70;

    public const int MAX_VARIANT_VALUE = 100;

    public const int MAX_MATERIAL = 200;

    public const int MAX_PRODUCT_TYPE = 750;

    public const int MAX_SHIPPING = 100;

    public const int MAX_SHIPPING_WEIGHT = 1_000_000;

    public const int MAX_MULTIPACK = 10_000;

    public const int MAX_CUSTOM_LABELS = 5;

    public const int MAX_CUSTOM_LABEL = 100;

    public string $id;

    public string $title;

    public ?string $description;

    public ?DateTimeImmutable $availabilityDate;

    public ?string $brand;

    public ?string $gtin;

    public ?string $mpn;

    public ?string $itemGroupId;

    public ?string $color;

    public ?string $size;

    public ?string $material;

    public ?string $pattern;

    public ?string $productType;

    public ?string $googleProductCategory;

    /** @var list<?string> the custom labels in their order, a blank one kept as none so that the others keep their number */
    public array $customLabels;

    /**
     * @param list<Url>      $additionalImageLinks
     * @param list<Shipping> $shipping             one per country and service the item ships by
     * @param int|null       $shippingWeight       the weight a carrier charges for, in grams
     * @param int|null       $multipack            the units of one product sold together as one item
     * @param bool           $isBundle             whether the item is a main product sold with others the store put together
     * @param list<string>   $customLabels         up to five labels the store groups its items by in a reader's campaigns
     */
    public function __construct(
        string $id,
        string $title,
        public Url $link,
        public Money $price,
        public Availability $availability,
        ?string $description = null,
        public ?Url $imageLink = null,
        public array $additionalImageLinks = [],
        ?DateTimeImmutable $availabilityDate = null,
        public ?Money $salePrice = null,
        public ?SalePeriod $salePeriod = null,
        public Condition $condition = Condition::New,
        ?string $brand = null,
        ?string $gtin = null,
        ?string $mpn = null,
        ?string $itemGroupId = null,
        ?string $color = null,
        ?string $size = null,
        ?string $material = null,
        ?string $pattern = null,
        ?string $productType = null,
        ?string $googleProductCategory = null,
        public ?AgeGroup $ageGroup = null,
        public ?Gender $gender = null,
        public array $shipping = [],
        public ?int $shippingWeight = null,
        public ?int $multipack = null,
        public bool $isBundle = false,
        array $customLabels = [],
    ) {
        $this->id = Text::identifier($id, 'identifier of an item', self::MAX_ID);
        $this->title = Text::required($title, 'title of '.$id, self::MAX_TITLE);
        $this->description = Text::optional($description, 'description of '.$id, self::MAX_DESCRIPTION, multiline: true);

        if (\count($additionalImageLinks) > self::MAX_ADDITIONAL_IMAGES) {
            throw InvalidFeedItem::because(\sprintf('The item %s has %d additional images at most.', $id, self::MAX_ADDITIONAL_IMAGES));
        }

        $this->checkPrices($id, $price, $salePrice, $salePeriod);

        if ($availabilityDate instanceof DateTimeImmutable && !$availability->takesADate()) {
            throw InvalidFeedItem::because(\sprintf('The item %s is %s: only a preorder or a backorder has an availability date.', $id, $availability->value));
        }

        $this->availabilityDate = $availabilityDate?->setTimezone(new DateTimeZone('UTC'));
        $this->brand = Text::optional($brand, 'brand of '.$id, self::MAX_BRAND);
        $this->gtin = null === $gtin ? null : Gtin::check($gtin);
        $this->mpn = Text::optional($mpn, 'MPN of '.$id, self::MAX_MPN);
        $this->itemGroupId = null === $itemGroupId ? null : Text::identifier($itemGroupId, 'group of '.$id, self::MAX_ID);
        $this->color = Text::optional($color, 'colour of '.$id, self::MAX_VARIANT_VALUE);
        $this->size = Text::optional($size, 'size of '.$id, self::MAX_VARIANT_VALUE);
        $this->material = Text::optional($material, 'material of '.$id, self::MAX_MATERIAL);
        $this->pattern = Text::optional($pattern, 'pattern of '.$id, self::MAX_VARIANT_VALUE);
        $this->productType = Text::optional($productType, 'product type of '.$id, self::MAX_PRODUCT_TYPE);
        $this->googleProductCategory = Text::optional($googleProductCategory, 'product category of '.$id, self::MAX_PRODUCT_TYPE);
        $this->checkShipping($id, $shipping, $shippingWeight, $multipack);

        if (\count($customLabels) > self::MAX_CUSTOM_LABELS) {
            throw InvalidFeedItem::because(\sprintf('The item %s has %d custom labels at most.', $id, self::MAX_CUSTOM_LABELS));
        }

        $this->customLabels = array_map(static fn (string $label): ?string => Text::optional($label, 'custom label of '.$id, self::MAX_CUSTOM_LABEL), $customLabels);
    }

    /**
     * Whether the item carries an identifier a reader matches it by — a GTIN or an MPN —; one that carries neither is
     * published as having none, as a handmade or custom product has.
     */
    public function hasIdentifier(): bool
    {
        return null !== $this->gtin || null !== $this->mpn;
    }

    /**
     * @param list<Shipping> $shipping
     */
    private function checkShipping(string $id, array $shipping, ?int $shippingWeight, ?int $multipack): void
    {
        if (\count($shipping) > self::MAX_SHIPPING) {
            throw InvalidFeedItem::because(\sprintf('The item %s has %d shipping costs at most.', $id, self::MAX_SHIPPING));
        }

        if (null !== $shippingWeight && ($shippingWeight < 1 || $shippingWeight > self::MAX_SHIPPING_WEIGHT)) {
            throw InvalidFeedItem::because(\sprintf('The shipping weight of %s is 1 to %d grams.', $id, self::MAX_SHIPPING_WEIGHT));
        }

        if (null !== $multipack && ($multipack < 2 || $multipack > self::MAX_MULTIPACK)) {
            throw InvalidFeedItem::because(\sprintf('A multipack of %s holds 2 to %d units.', $id, self::MAX_MULTIPACK));
        }
    }

    private function checkPrices(string $id, Money $price, ?Money $salePrice, ?SalePeriod $salePeriod): void
    {
        if ($price->isNegative()) {
            throw InvalidFeedItem::because(\sprintf('The item %s has a negative price.', $id));
        }

        if (!$salePrice instanceof Money) {
            if ($salePeriod instanceof SalePeriod) {
                throw InvalidFeedItem::because(\sprintf('The item %s has a sale period but no sale price.', $id));
            }

            return;
        }

        if (!$salePrice->currency->equals($price->currency)) {
            throw InvalidFeedItem::because(\sprintf('The sale price of %s is in %s, its price in %s.', $id, $salePrice->currency->code, $price->currency->code));
        }

        if ($salePrice->isNegative() || !$salePrice->isLessThan($price)) {
            throw InvalidFeedItem::because(\sprintf('The sale price of %s is below its price, and not negative.', $id));
        }
    }
}
