<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A product of the catalogue, named by its SKU: what it is, its base price, its tax category, and its attributes —
 * colour, size, format, as the application names them.
 *
 * The base price is the one a line takes when no price book gives another. A product is withdrawn rather than
 * deleted, so that the orders and receipts that name it keep their meaning; a withdrawn product is not sold.
 *
 * - A **family** names the axes its variants differ by — colour, size —, and is chosen through them: it is not sold
 *   itself. A **variant** is a product of its own, with its SKU, price and stock, that gives each axis of its family
 *   one value.
 * - A **composite** product is made of others, each with a number of units: it is sold as one product, on one line,
 *   at its own price and in its own tax category, the whole of it taxed together.
 *
 * Its name is the one of its own text; its translations, one per locale, live on `ProductTranslation`.
 *
 * Its **price rules**:
 *
 * - **Pay what you want**: the buyer chooses the price, from the base price — the minimum — up to a maximum, its
 *   own or the site's default, with an optional suggested price shown first.
 * - **Community copies**: other buyers fund copies at a copy price, which people who cannot pay claim.
 *
 * A physical product may be tracked by **serial numbers**, which the stock of the logistics module holds.
 *
 * Its **suggested retail price** is the one its maker or distributor suggests — the list price a sign compares the
 * shop's price with —: kept beside the base price, it never prices a line.
 *
 * Its **offer** says what the channels that sell it offer of it: listed, reached by its direct link only, or hidden;
 * orderable or not — announced, sold out for good —; its price shown or not. A channel outside its selection, or a
 * withdrawn product, hides it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_catalog_product')]
#[ORM\Index(columns: ['active'])]
#[ORM\Index(columns: ['family'])]
final class Product implements Translatable
{
    /** The axes of a family, at most. */
    public const int MAX_AXES = 5;

    /** The parts of a composite product, at most. */
    public const int MAX_COMPONENTS = 50;

    public Money $price {
        get => Money::of($this->priceMinorUnits, Currency::of($this->currencyCode));
    }

    #[ORM\Column(name: 'price', type: 'bigint')]
    private int $priceMinorUnits;

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    /** @var array<string, string> */
    #[ORM\Column(name: 'attributes', type: 'json')]
    private array $attributes = [];

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Column(name: 'visibility', length: 16, enumType: Visibility::class, options: ['default' => 'listed'])]
    private Visibility $visibility = Visibility::Listed;

    #[ORM\Column(name: 'orderable', options: ['default' => true])]
    private bool $orderable = true;

    #[ORM\Column(name: 'price_shown', options: ['default' => true])]
    private bool $priceShown = true;

    /** @var list<string> the channels that sell it; every channel when empty */
    #[ORM\Column(name: 'channels', type: 'json')]
    private array $channels = [];

    /** The SKU of the family of a variant. */
    #[ORM\Column(name: 'family', length: 64, nullable: true)]
    private ?string $family = null;

    /** @var list<string> the axes a family's variants differ by */
    #[ORM\Column(name: 'axes', type: 'json')]
    private array $axes = [];

    /** @var array<string, string> axis → value, for a variant */
    #[ORM\Column(name: 'options', type: 'json')]
    private array $options = [];

    /** @var list<array{sku: string, quantity: int}> */
    #[ORM\Column(name: 'components', type: 'json')]
    private array $components = [];

    /** Whether each unit sold carries a serial number. */
    #[ORM\Column(name: 'serial_numbers')]
    private bool $serialNumbers = false;

    /** Whether the buyer chooses the price, from the base price up. */
    #[ORM\Column(name: 'pay_what_you_want')]
    private bool $payWhatYouWant = false;

    /** The price shown first when the buyer chooses it, in minor units. */
    #[ORM\Column(name: 'suggested_price', type: 'bigint', nullable: true)]
    private ?int $suggestedPrice = null;

    /** The highest price a buyer may choose, in minor units. */
    #[ORM\Column(name: 'maximum_price', type: 'bigint', nullable: true)]
    private ?int $maximumPrice = null;

    /** The price of a community copy, in minor units; none when the product offers none. */
    #[ORM\Column(name: 'community_copy_price', type: 'bigint', nullable: true)]
    private ?int $communityCopyPrice = null;

    /** The retail price its maker or distributor suggests, in minor units; none when no one suggests one. */
    #[ORM\Column(name: 'suggested_retail_price', type: 'bigint', nullable: true)]
    private ?int $suggestedRetailPrice = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'kind', length: 16, enumType: ProductKind::class)]
        public readonly ProductKind $kind,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        Money $price,
        #[ORM\Column(name: 'tax_category', length: 64)]
        private string $taxCategory,
    ) {
        $this->priceMinorUnits = $price->minorUnits;
        $this->currencyCode = $price->currency->code;
    }

    public static function create(string $sku, ProductKind $kind, string $name, Money $price, string $taxCategory): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $sku)) {
            throw InvalidValue::because(\sprintf('"%s" is not a SKU: up to 64 letters, digits, ".", "_" or "-", starting with a letter or digit.', $sku));
        }

        return new self($sku, $kind, self::checkedName($name), self::checkedPrice($sku, $price), self::checkedCategory($taxCategory));
    }

    /**
     * A variant of a family: its kind, tax category and channels are the family's, and its name and price too unless
     * given. Each axis of the family takes one value, and no other option is given.
     *
     * @param array<string, string> $options axis → value
     */
    public static function variant(self $family, string $sku, array $options, ?Money $price = null, ?string $name = null): self
    {
        if (!$family->isFamily()) {
            throw InvalidValue::because(\sprintf('The product %s names no axes: it has no variants.', $family->sku));
        }

        $values = [];

        foreach ($family->axes as $axis) {
            $value = $options[$axis] ?? null;

            if (null === $value || '' === trim($value) || mb_strlen($value) > 64) {
                throw InvalidValue::because(\sprintf('A variant of %s gives its axis %s a value of 1 to 64 characters, not blank.', $family->sku, $axis));
            }

            $values[$axis] = $value;
        }

        if (\count($options) !== \count($values)) {
            throw InvalidValue::because(\sprintf('A variant of %s gives values to its axes %s only.', $family->sku, implode(', ', $family->axes)));
        }

        $variant = self::create($sku, $family->kind, $name ?? $family->name.' — '.implode(' / ', $values), $price ?? $family->price, $family->taxCategory);
        $variant->family = $family->sku;
        $variant->options = $values;
        $variant->channels = $family->channels;
        $variant->visibility = $family->visibility;
        $variant->orderable = $family->orderable;
        $variant->priceShown = $family->priceShown;
        $variant->serialNumbers = $family->serialNumbers;

        return $variant;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function taxCategory(): string
    {
        return $this->taxCategory;
    }

    /**
     * @return array<string, string> attribute → value, by attribute
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($name);
    }

    /**
     * A new base price, in the product's currency, for the lines priced from now on; when the buyer chooses the price,
     * it stays the minimum, not above the suggested price nor the maximum.
     */
    public function reprice(Money $price): void
    {
        if (!$price->currency->equals($this->price->currency)) {
            throw CatalogRefused::currency(\sprintf('The product %s', $this->sku), $this->currencyCode, $price->currency->code);
        }

        $price = self::checkedPrice($this->sku, $price);

        if ($price->minorUnits > ($this->suggestedPrice ?? $this->maximumPrice ?? \PHP_INT_MAX)) {
            throw InvalidValue::because(\sprintf('The product %s is paid from its base price up: its base price stays below its suggested price and its maximum.', $this->sku));
        }

        $this->priceMinorUnits = $price->minorUnits;
    }

    public function classify(string $taxCategory): void
    {
        $this->taxCategory = self::checkedCategory($taxCategory);
    }

    /**
     * Sets an attribute; a value of `null` removes it.
     */
    public function describe(string $attribute, ?string $value): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $attribute)) {
            throw InvalidValue::because(\sprintf('"%s" is not an attribute name: up to 64 lowercase letters, digits or "_", starting with a letter.', $attribute));
        }

        if (null === $value) {
            unset($this->attributes[$attribute]);

            return;
        }

        if ('' === trim($value) || mb_strlen($value) > 255) {
            throw InvalidValue::because(\sprintf('The attribute %s of %s holds 1 to 255 characters, not blank.', $attribute, $this->sku));
        }

        $this->attributes[$attribute] = $value;
        ksort($this->attributes);
    }

    /**
     * @return list<string> the channels that sell it; every channel when empty
     */
    public function channels(): array
    {
        return $this->channels;
    }

    /**
     * The channels that sell it, by their codes; an empty list sells it through every channel.
     *
     * @param list<string> $channels
     */
    public function sellThrough(array $channels): void
    {
        foreach ($channels as $channel) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $channel)) {
                throw InvalidValue::because(\sprintf('"%s" is not a channel: 1 to 64 visible ASCII characters.', $channel));
            }
        }

        $this->channels = array_values(array_unique($channels));
    }

    /**
     * Whether the channel sells it; an order without a channel is sold only what every channel sells.
     */
    public function isSoldThrough(?string $channel): bool
    {
        return [] === $this->channels || \in_array($channel, $this->channels, true);
    }

    /**
     * What the channels that sell it offer of it; listed, orderable and priced for a new product.
     */
    public function offer(): Offer
    {
        return Offer::of($this->visibility, $this->orderable, $this->priceShown);
    }

    /**
     * Lists it, leaves it to its direct link or hides it, orderable or not, its price shown or not, in every channel
     * that sells it.
     */
    public function changeOffer(Offer $offer): void
    {
        $this->visibility = $offer->visibility;
        $this->orderable = $offer->orderable;
        $this->priceShown = $offer->priceShown;
    }

    /**
     * What the channel offers of it: hidden when withdrawn or outside the channel's selection, its own offer otherwise.
     * A variant is also restricted by its family, which `ProductAvailability` reads.
     */
    public function offerThrough(?string $channel): Offer
    {
        return $this->active && $this->isSoldThrough($channel) ? $this->offer() : Offer::hidden();
    }

    /**
     * Makes the product a family whose variants differ by these axes, in the order a buyer chooses them.
     *
     * @param list<string> $axes
     */
    public function defineAxes(array $axes): void
    {
        $axes = array_values(array_unique($axes));

        if (null !== $this->family || [] !== $this->components) {
            throw InvalidValue::because(\sprintf('The product %s is a variant or a composite: it names no axes.', $this->sku));
        }

        if (\count($axes) > self::MAX_AXES) {
            throw InvalidValue::because(\sprintf('A family has up to %d axes.', self::MAX_AXES));
        }

        foreach ($axes as $axis) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $axis)) {
                throw InvalidValue::because(\sprintf('"%s" is not an axis name: up to 64 lowercase letters, digits or "_", starting with a letter.', $axis));
            }
        }

        $this->axes = $axes;
    }

    /**
     * @return list<string> the axes its variants differ by; none for a product that is no family
     */
    public function axes(): array
    {
        return $this->axes;
    }

    /**
     * Whether it is a family, chosen through its variants and never sold itself.
     */
    public function isFamily(): bool
    {
        return [] !== $this->axes;
    }

    /**
     * The SKU of its family, for a variant.
     */
    public function family(): ?string
    {
        return $this->family;
    }

    /**
     * @return array<string, string> axis → value, in the order of the family's axes; none for a product that is no variant
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Makes it a composite of these parts; none makes it a product of its own again. A family is no composite, and no
     * part is the product itself or named twice.
     *
     * @param list<ProductComponent> $components
     */
    public function composeOf(array $components): void
    {
        if ([] !== $components && $this->isFamily()) {
            throw InvalidValue::because(\sprintf('The product %s is a family: it is chosen through its variants, not made of parts.', $this->sku));
        }

        if (\count($components) > self::MAX_COMPONENTS) {
            throw InvalidValue::because(\sprintf('A composite product has up to %d parts.', self::MAX_COMPONENTS));
        }

        $parts = [];

        foreach ($components as $component) {
            if ($component->sku === $this->sku || isset($parts[$component->sku])) {
                throw InvalidValue::because(\sprintf('The product %s names %s once at most, and never itself.', $this->sku, $component->sku));
            }

            $parts[$component->sku] = $component->toArray();
        }

        $this->components = array_values($parts);
    }

    /**
     * @return list<ProductComponent> its parts; none for a product of its own
     */
    public function components(): array
    {
        return array_map(ProductComponent::fromArray(...), $this->components);
    }

    public function isComposite(): bool
    {
        return [] !== $this->components;
    }

    /**
     * Whether each unit sold carries a serial number, which the stock of the logistics module holds; only a physical
     * product is tracked so.
     */
    public function trackSerialNumbers(bool $track = true): void
    {
        if ($track && ProductKind::Physical !== $this->kind) {
            throw InvalidValue::because(\sprintf('The product %s is not physical: it carries no serial numbers.', $this->sku));
        }

        $this->serialNumbers = $track;
    }

    public function tracksSerialNumbers(): bool
    {
        return $this->serialNumbers;
    }

    /**
     * Lets the buyer choose the price, from the base price — the minimum — up to the maximum, or the site's default
     * maximum when none is given; the suggested price, when given, lies between them and is shown first.
     */
    public function payWhatYouWant(?Money $suggested = null, ?Money $maximum = null): void
    {
        $minimum = $this->price;

        foreach ([$suggested, $maximum] as $price) {
            if ($price instanceof Money && !$price->currency->equals($minimum->currency)) {
                throw CatalogRefused::currency(\sprintf('The product %s', $this->sku), $this->currencyCode, $price->currency->code);
            }
        }

        if (($suggested instanceof Money && $suggested->isLessThan($minimum))
            || ($maximum instanceof Money && $maximum->isLessThan($suggested ?? $minimum))) {
            throw InvalidValue::because(\sprintf('The product %s is paid from its base price up: the suggested price, then the maximum, are not below it.', $this->sku));
        }

        $this->payWhatYouWant = true;
        $this->suggestedPrice = $suggested?->minorUnits;
        $this->maximumPrice = $maximum?->minorUnits;
    }

    /**
     * Sells the product at its price again.
     */
    public function fixPrice(): void
    {
        $this->payWhatYouWant = false;
        $this->suggestedPrice = null;
        $this->maximumPrice = null;
    }

    public function isPayWhatYouWant(): bool
    {
        return $this->payWhatYouWant;
    }

    public function suggestedPrice(): ?Money
    {
        return null === $this->suggestedPrice ? null : Money::of($this->suggestedPrice, $this->price->currency);
    }

    public function maximumPrice(): ?Money
    {
        return null === $this->maximumPrice ? null : Money::of($this->maximumPrice, $this->price->currency);
    }

    /**
     * The prices a buyer may choose: from the base price up to the product's maximum, or the default maximum given when
     * it has none; `null` for a product at a fixed price, or without a maximum, or whose default maximum is in another
     * currency or below its suggested price — below its base price when none is suggested.
     */
    public function chosenPrices(?Money $defaultMaximum = null): ?AmountRange
    {
        $maximum = $this->maximumPrice() ?? $defaultMaximum;

        if (!$this->payWhatYouWant || !$maximum instanceof Money || !$maximum->currency->equals($this->price->currency) || $maximum->isLessThan($this->suggestedPrice() ?? $this->price)) {
            return null;
        }

        return AmountRange::of($this->price, $maximum);
    }

    /**
     * Offers community copies at this price, which other buyers fund and people who cannot pay claim; `null` offers
     * none, and keeps what was funded and claimed.
     */
    public function offerCommunityCopies(?Money $copyPrice): void
    {
        if ($copyPrice instanceof Money && !$copyPrice->currency->equals($this->price->currency)) {
            throw CatalogRefused::currency(\sprintf('The product %s', $this->sku), $this->currencyCode, $copyPrice->currency->code);
        }

        if ($copyPrice instanceof Money && !$copyPrice->isPositive()) {
            throw InvalidValue::because(\sprintf('A community copy of %s costs more than zero.', $this->sku));
        }

        $this->communityCopyPrice = $copyPrice?->minorUnits;
    }

    public function communityCopyPrice(): ?Money
    {
        return null === $this->communityCopyPrice ? null : Money::of($this->communityCopyPrice, $this->price->currency);
    }

    /**
     * Keeps the retail price its maker or distributor suggests, more than zero and in the product's currency; `null`
     * forgets it. It never prices a line: the base price and the price books do.
     */
    public function suggestRetailPrice(?Money $price): void
    {
        if ($price instanceof Money && !$price->currency->equals($this->price->currency)) {
            throw CatalogRefused::currency(\sprintf('The product %s', $this->sku), $this->currencyCode, $price->currency->code);
        }

        if ($price instanceof Money && !$price->isPositive()) {
            throw InvalidValue::because(\sprintf('The suggested retail price of %s is more than zero.', $this->sku));
        }

        $this->suggestedRetailPrice = $price?->minorUnits;
    }

    public function suggestedRetailPrice(): ?Money
    {
        return null === $this->suggestedRetailPrice ? null : Money::of($this->suggestedRetailPrice, $this->price->currency);
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }

    /**
     * @return class-string<ProductTranslation>
     */
    #[Override]
    public static function translationClass(): string
    {
        return ProductTranslation::class;
    }

    /**
     * @return non-empty-list<string>
     */
    #[Override]
    public static function translatedFields(): array
    {
        return ['name'];
    }

    #[Override]
    public function newTranslation(Locale $locale): ProductTranslation
    {
        return new ProductTranslation($this, $locale);
    }

    private static function checkedName(string $name): string
    {
        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because('A product name holds 1 to 255 characters, not blank.');
        }

        return $name;
    }

    private static function checkedPrice(string $sku, Money $price): Money
    {
        if ($price->isNegative()) {
            throw InvalidValue::because(\sprintf('The product %s has a negative price.', $sku));
        }

        return $price;
    }

    private static function checkedCategory(string $taxCategory): string
    {
        if ('' === $taxCategory || \strlen($taxCategory) > 64) {
            throw InvalidValue::because('A tax category holds 1 to 64 characters.');
        }

        return $taxCategory;
    }
}
