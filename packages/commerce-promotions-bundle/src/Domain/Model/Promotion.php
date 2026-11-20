<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A promotion: what it gives, on which products, and where, to whom and when it applies.
 *
 * - **Where**: a list of channels — the web site, the stores, both — or every channel; for free shipping, a list of
 *   delivery methods, or every method.
 * - **To whom**: a list of customer groups, or everyone; when it asks for one, a code the buyer entered; when it
 *   asks for them, products the customer already owns; and a limit of uses per customer.
 * - **When**: a period, the start included and the end excluded, and a limit of uses.
 * - **On what**: a list of SKUs, or every product, less the SKUs it excludes.
 * - **With what**: the promotions it applies after, and those it never combines with.
 *
 * Promotions apply in the order their `after` lists set, by code otherwise; an exclusive one that applies stops the
 * others. Its label is the one receipts show; its translations, one per locale, live on `PromotionTranslation`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_promotion')]
#[ORM\Index(columns: ['active'])]
final class Promotion implements Translatable
{
    /** The products, channels, groups, methods or promotions a condition lists, at most. */
    public const int MAX_LIST = 500;

    /** The groups of a bundle, at most. */
    public const int MAX_GROUPS = 10;

    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    #[ORM\Column(name: 'active')]
    private bool $active = true;

    #[ORM\Column(name: 'uses', type: 'integer')]
    private int $uses = 0;

    /**
     * @param list<array{skus: list<string>, quantity: int}> $groups
     * @param list<string>                                   $skus
     * @param list<string>                                   $excludedSkus
     * @param list<string>                                   $channels
     * @param list<string>                                   $customerGroups
     * @param list<string>                                   $deliveryMethods
     * @param list<string>                                   $ownedSkus
     * @param list<string>                                   $after
     * @param list<string>                                   $incompatibleWith
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'code', length: 64)]
        public readonly string $code,
        #[ORM\Column(name: 'label', length: 255)]
        private string $label,
        #[ORM\Column(name: 'exclusive')]
        public readonly bool $exclusive,
        #[ORM\Column(name: 'action', length: 16, enumType: PromotionAction::class)]
        public readonly PromotionAction $action,
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private readonly string $currencyCode,
        #[ORM\Column(name: 'parts_per_million', type: 'integer')]
        public readonly int $partsPerMillion,
        #[ORM\Column(name: 'amount', type: 'bigint')]
        private readonly int $amountMinorUnits,
        #[ORM\Column(name: 'buy_quantity', type: 'smallint')]
        public readonly int $buyQuantity,
        #[ORM\Column(name: 'pay_quantity', type: 'smallint')]
        public readonly int $payQuantity,
        #[ORM\Column(name: 'bundle_groups', type: 'json')]
        private readonly array $groups,
        #[ORM\Column(name: 'minimum_subtotal', type: 'bigint')]
        private readonly int $minimumSubtotalMinorUnits,
        #[ORM\Column(name: 'skus', type: 'json')]
        public readonly array $skus,
        #[ORM\Column(name: 'excluded_skus', type: 'json')]
        public readonly array $excludedSkus,
        #[ORM\Column(name: 'channels', type: 'json')]
        public readonly array $channels,
        #[ORM\Column(name: 'customer_groups', type: 'json')]
        public readonly array $customerGroups,
        #[ORM\Column(name: 'delivery_methods', type: 'json')]
        public readonly array $deliveryMethods,
        #[ORM\Column(name: 'owned_skus', type: 'json')]
        public readonly array $ownedSkus,
        #[ORM\Column(name: 'requires_code')]
        public readonly bool $requiresCode,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable', nullable: true)]
        public readonly ?DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable', nullable: true)]
        public readonly ?DateTimeImmutable $endsAt,
        #[ORM\Column(name: 'usage_limit', type: 'integer', nullable: true)]
        public readonly ?int $usageLimit,
        #[ORM\Column(name: 'per_customer_limit', type: 'integer', nullable: true)]
        public readonly ?int $perCustomerLimit,
        #[ORM\Column(name: 'after_promotions', type: 'json')]
        public readonly array $after,
        #[ORM\Column(name: 'incompatible_with', type: 'json')]
        public readonly array $incompatibleWith,
    ) {
    }

    /**
     * A share off each eligible line: `100_000` ppm is 10 %.
     */
    public static function percentOff(string $code, string $label, int $partsPerMillion, Currency $currency): self
    {
        if ($partsPerMillion < 1 || $partsPerMillion > 1_000_000) {
            throw InvalidValue::because(\sprintf('A share off lies between 1 and 1000000 parts per million; %d does not.', $partsPerMillion));
        }

        return self::make($code, $label, PromotionAction::PercentOff, $currency, partsPerMillion: $partsPerMillion);
    }

    /**
     * An amount off the order, spread over the eligible lines.
     */
    public static function amountOff(string $code, string $label, Money $amount): self
    {
        if (!$amount->isPositive()) {
            throw InvalidValue::because(\sprintf('The promotion %s takes an amount above zero off.', $code));
        }

        return self::make($code, $label, PromotionAction::AmountOff, $amount->currency, amount: $amount->minorUnits);
    }

    /**
     * N units for the price of X: `buyNPayX(..., 3, 2)` is three for two.
     */
    public static function buyNPayX(string $code, string $label, int $buy, int $pay, Currency $currency): self
    {
        if ($pay < 0 || $buy <= $pay || $buy > 1_000) {
            throw InvalidValue::because(\sprintf('A promotion gives more units than it charges, up to 1000: %d for %d does not.', $buy, $pay));
        }

        return self::make($code, $label, PromotionAction::BuyNPayX, $currency, buy: $buy, pay: $pay);
    }

    /**
     * A price for each complete bundle of the groups: a rulebook and two dice sets for 50 $. No product belongs to two
     * groups. Each product of a bundle keeps its own line, discounted by its share.
     *
     * @param list<BundleGroup> $groups
     */
    public static function bundlePrice(string $code, string $label, array $groups, Money $price): self
    {
        if ([] === $groups || \count($groups) > self::MAX_GROUPS) {
            throw InvalidValue::because(\sprintf('A bundle holds 1 to %d groups; %d is not.', self::MAX_GROUPS, \count($groups)));
        }

        $seen = [];

        foreach ($groups as $group) {
            foreach ($group->skus as $sku) {
                if (\in_array($sku, $seen, true)) {
                    throw InvalidValue::because(\sprintf('The product %s belongs to two groups of the bundle %s.', $sku, $code));
                }

                $seen[] = $sku;
            }
        }

        if ($price->isNegative()) {
            throw InvalidValue::because(\sprintf('The bundle %s has a price of zero or more.', $code));
        }

        return self::make($code, $label, PromotionAction::BundlePrice, $price->currency, amount: $price->minorUnits, groups: array_map(static fn (BundleGroup $group): array => $group->toArray(), $groups));
    }

    /**
     * The delivery charge waived, for the methods `when(deliveryMethods: ...)` lists, or every method.
     */
    public static function freeShipping(string $code, string $label, Currency $currency): self
    {
        return self::make($code, $label, PromotionAction::FreeShipping, $currency);
    }

    /**
     * A copy with its conditions, made before the promotion is stored; each argument left out keeps the current one.
     *
     * @param list<string>|null $skus             the products it applies to; every product when empty
     * @param list<string>|null $excludedSkus     the products it never discounts, even when `skus` lists them
     * @param list<string>|null $channels         the channels it applies in; every channel when empty
     * @param list<string>|null $customerGroups   the groups it applies to; everyone when empty
     * @param list<string>|null $deliveryMethods  the delivery methods a free shipping waives; every method when empty
     * @param list<string>|null $ownedSkus        products the customer owns one of, at least, for it to apply
     * @param int|null          $perCustomerLimit the uses each customer gets; a guest gets none
     * @param list<string>|null $after            the promotions it applies after, when they apply too
     * @param list<string>|null $incompatibleWith the promotions it never combines with: the first that applies wins
     */
    public function when(
        ?array $skus = null,
        ?array $excludedSkus = null,
        ?array $channels = null,
        ?array $customerGroups = null,
        ?array $deliveryMethods = null,
        ?array $ownedSkus = null,
        ?bool $requiresCode = null,
        ?DateTimeImmutable $startsAt = null,
        ?DateTimeImmutable $endsAt = null,
        ?int $usageLimit = null,
        ?int $perCustomerLimit = null,
        ?Money $minimumSubtotal = null,
        ?array $after = null,
        ?array $incompatibleWith = null,
        ?bool $exclusive = null,
    ): self {
        if ($minimumSubtotal instanceof Money && !$minimumSubtotal->currency->equals($this->currency)) {
            throw InvalidValue::because(\sprintf('The minimum of the promotion %s counts in %s.', $this->code, $this->currencyCode));
        }

        return self::make(
            $this->code,
            $this->label,
            $this->action,
            $this->currency,
            $this->partsPerMillion,
            $this->amountMinorUnits,
            $this->buyQuantity,
            $this->payQuantity,
            $this->groups,
            $minimumSubtotal->minorUnits ?? $this->minimumSubtotalMinorUnits,
            $skus ?? $this->skus,
            $excludedSkus ?? $this->excludedSkus,
            $channels ?? $this->channels,
            $customerGroups ?? $this->customerGroups,
            $deliveryMethods ?? $this->deliveryMethods,
            $ownedSkus ?? $this->ownedSkus,
            $requiresCode ?? $this->requiresCode,
            $startsAt ?? $this->startsAt,
            $endsAt ?? $this->endsAt,
            $usageLimit ?? $this->usageLimit,
            $perCustomerLimit ?? $this->perCustomerLimit,
            $after ?? $this->after,
            $incompatibleWith ?? $this->incompatibleWith,
            $exclusive ?? $this->exclusive,
        );
    }

    /**
     * The label in the promotion's own text; `PromotionTexts` reads it in a locale.
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * A new label, in the promotion's own text, for the receipts of the orders priced from now on.
     */
    public function relabel(string $label): void
    {
        $this->label = self::checkedLabel($this->code, $label);
    }

    /**
     * The amount off of an `AmountOff` promotion, the price of a bundle of a `BundlePrice` one; zero for the others.
     */
    public function amount(): Money
    {
        return Money::of($this->amountMinorUnits, $this->currency);
    }

    /**
     * @return list<BundleGroup> the groups of a `BundlePrice` promotion; none for the others
     */
    public function groups(): array
    {
        return array_map(BundleGroup::fromArray(...), $this->groups);
    }

    public function minimumSubtotal(): Money
    {
        return Money::of($this->minimumSubtotalMinorUnits, $this->currency);
    }

    public function uses(): int
    {
        return $this->uses;
    }

    public function isActive(): bool
    {
        return $this->active;
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
     * Whether it may apply to an order sold through this channel to this group at this moment, in this currency, uses
     * left; the code, the customer, the delivery method and the products are checked by the evaluator.
     */
    public function appliesTo(?string $channel, ?string $customerGroup, Currency $currency, DateTimeImmutable $at): bool
    {
        return $this->active
            && $currency->equals($this->currency)
            && ([] === $this->channels || \in_array($channel, $this->channels, true))
            && ([] === $this->customerGroups || \in_array($customerGroup, $this->customerGroups, true))
            && (!$this->startsAt instanceof DateTimeImmutable || $at >= $this->startsAt)
            && (!$this->endsAt instanceof DateTimeImmutable || $at < $this->endsAt)
            && (null === $this->usageLimit || $this->uses < $this->usageLimit);
    }

    /**
     * Whether it discounts the product: listed, or every product, and not excluded.
     */
    public function coversSku(string $sku): bool
    {
        return ([] === $this->skus || \in_array($sku, $this->skus, true)) && !\in_array($sku, $this->excludedSkus, true);
    }

    /**
     * Whether a free shipping waives this delivery method; an order without one has nothing to waive.
     */
    public function waives(?string $deliveryMethod): bool
    {
        return null !== $deliveryMethod && ([] === $this->deliveryMethods || \in_array($deliveryMethod, $this->deliveryMethods, true));
    }

    /**
     * Whether it never combines with the other: either one lists the other.
     */
    public function isIncompatibleWith(self $other): bool
    {
        return \in_array($other->code, $this->incompatibleWith, true) || \in_array($this->code, $other->incompatibleWith, true);
    }

    /**
     * Counts one use, once the order it applied to is placed: `false`, and nothing counted, when its limit is reached.
     * The Doctrine adapter of `PromotionRepository` claims the same way in one atomic `UPDATE`.
     */
    public function claim(): bool
    {
        if (null !== $this->usageLimit && $this->uses >= $this->usageLimit) {
            return false;
        }

        ++$this->uses;

        return true;
    }

    /**
     * Gives back a use claimed for an order whose other claims failed.
     */
    public function release(): void
    {
        $this->uses = max(0, $this->uses - 1);
    }

    /**
     * @return class-string<PromotionTranslation>
     */
    #[Override]
    public static function translationClass(): string
    {
        return PromotionTranslation::class;
    }

    /**
     * @return non-empty-list<string>
     */
    #[Override]
    public static function translatedFields(): array
    {
        return ['label'];
    }

    #[Override]
    public function newTranslation(Locale $locale): PromotionTranslation
    {
        return new PromotionTranslation($this, $locale);
    }

    /**
     * @param list<array{skus: list<string>, quantity: int}> $groups
     * @param list<string>                                   $skus
     * @param list<string>                                   $excludedSkus
     * @param list<string>                                   $channels
     * @param list<string>                                   $customerGroups
     * @param list<string>                                   $deliveryMethods
     * @param list<string>                                   $ownedSkus
     * @param list<string>                                   $after
     * @param list<string>                                   $incompatibleWith
     */
    private static function make(
        string $code,
        string $label,
        PromotionAction $action,
        Currency $currency,
        int $partsPerMillion = 0,
        int $amount = 0,
        int $buy = 0,
        int $pay = 0,
        array $groups = [],
        int $minimumSubtotal = 0,
        array $skus = [],
        array $excludedSkus = [],
        array $channels = [],
        array $customerGroups = [],
        array $deliveryMethods = [],
        array $ownedSkus = [],
        bool $requiresCode = false,
        ?DateTimeImmutable $startsAt = null,
        ?DateTimeImmutable $endsAt = null,
        ?int $usageLimit = null,
        ?int $perCustomerLimit = null,
        array $after = [],
        array $incompatibleWith = [],
        bool $exclusive = false,
    ): self {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a promotion code: up to 64 lowercase letters, digits, "_", "." or "-".', $code));
        }

        $label = self::checkedLabel($code, $label);
        $skus = self::checkedList($code, $skus);
        $excludedSkus = self::checkedList($code, $excludedSkus);
        $channels = self::checkedList($code, $channels);
        $customerGroups = self::checkedList($code, $customerGroups);
        $deliveryMethods = self::checkedList($code, $deliveryMethods);
        $ownedSkus = self::checkedList($code, $ownedSkus);
        $after = self::checkedList($code, $after);
        $incompatibleWith = self::checkedList($code, $incompatibleWith);

        if ($minimumSubtotal < 0 || (null !== $usageLimit && $usageLimit < 1) || (null !== $perCustomerLimit && $perCustomerLimit < 1)) {
            throw InvalidValue::because(\sprintf('The promotion %s has a minimum of zero or more and limits of one use or more.', $code));
        }

        if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable && $endsAt <= $startsAt) {
            throw InvalidValue::because(\sprintf('The promotion %s ends after it starts.', $code));
        }

        if (\in_array($code, $after, true) || \in_array($code, $incompatibleWith, true)) {
            throw InvalidValue::because(\sprintf('The promotion %s is never ordered after itself, nor incompatible with itself.', $code));
        }

        return new self($code, $label, $exclusive, $action, $currency->code, $partsPerMillion, $amount, $buy, $pay, $groups, $minimumSubtotal, $skus, $excludedSkus, $channels, $customerGroups, $deliveryMethods, $ownedSkus, $requiresCode, $startsAt, $endsAt, $usageLimit, $perCustomerLimit, $after, $incompatibleWith);
    }

    /**
     * @param list<string> $list
     *
     * @return list<string> without repetitions
     */
    private static function checkedList(string $code, array $list): array
    {
        $list = array_values(array_unique($list));

        if (\count($list) > self::MAX_LIST) {
            throw InvalidValue::because(\sprintf('A condition of the promotion %s lists up to %d names.', $code, self::MAX_LIST));
        }

        foreach ($list as $name) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $name)) {
                throw InvalidValue::because(\sprintf('"%s" is not a SKU, a channel, a customer group, a delivery method or a promotion: 1 to 64 visible ASCII characters.', $name));
            }
        }

        return $list;
    }

    private static function checkedLabel(string $code, string $label): string
    {
        if ('' === trim($label) || mb_strlen($label) > 255) {
            throw InvalidValue::because(\sprintf('The promotion %s has a label of 1 to 255 characters, which the receipt shows.', $code));
        }

        return $label;
    }
}
