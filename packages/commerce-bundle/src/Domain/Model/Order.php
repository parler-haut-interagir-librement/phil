<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Exception\LineNotInOrder;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;

/**
 * An order being built: its lines, one per thing sold with its option values, and the adjustments the order
 * processors computed on them.
 *
 * **Every amount is in the order's one currency**, and **the total is always computed**, never stored: the sum of
 * the line subtotals and of the adjustments that are not neutral. The order does not price or tax itself: the
 * application service changes its lines, then runs the processors, each of which replaces the adjustments of its
 * own type.
 *
 * **Only a cart changes.** Placing an order freezes its lines and adjustments; from then on only its state moves.
 *
 * The order is stored whole, in one row: its lines and adjustments are values of the order, in two columns of
 * their own types, never entities of their own. A version column refuses a write made over a stale copy.
 *
 * Beside its lines, an order keeps its customer, how it is offered as a gift, the wish list it buys from, the
 * discounts granted by hand, a status of the application's attached to its state, and its revision, which each
 * confirmed change of the placed order moves on.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_order')]
#[ORM\Index(columns: ['state', 'placed_at'])]
final class Order
{
    /** The order's one currency, read from its stored code. */
    public Currency $currency {
        get => Currency::of($this->currencyCode);
    }

    #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
    private string $currencyCode;

    /** @var array<string, OrderLine> keyed by the reference's value */
    #[ORM\Column(name: '`lines`', type: 'phil_commerce_order_lines')]
    private array $lines = [];

    /** @var list<Adjustment> */
    #[ORM\Column(name: 'adjustments', type: 'phil_commerce_adjustments')]
    private array $adjustments = [];

    #[ORM\Column(name: 'tax_zone', length: 64, nullable: true)]
    private ?string $taxZone = null;

    #[ORM\Column(name: 'tax_included', type: 'boolean', options: ['default' => false])]
    private bool $taxIncluded = false;

    #[ORM\Column(name: 'channel', length: 64, nullable: true)]
    private ?string $channel = null;

    #[ORM\Column(name: 'customer_group', length: 64, nullable: true)]
    private ?string $customerGroup = null;

    #[ORM\Column(name: 'delivery_method', length: 64, nullable: true)]
    private ?string $deliveryMethod = null;

    /** @var list<string> the promotion codes the buyer entered, in the order entered */
    #[ORM\Column(name: 'codes', type: 'json')]
    private array $codes = [];

    #[ORM\Column(name: 'state', length: 16, enumType: OrderState::class)]
    private OrderState $state = OrderState::Cart;

    #[ORM\Column(name: 'placed_at', nullable: true)]
    private ?DateTimeImmutable $placedAt = null;

    #[ORM\Column(name: 'cancelled_at', nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'customer', length: 255, nullable: true)]
    private ?string $customer = null;

    /** @var array{wrapped: bool, recycled_wrap: bool, message: string|null, prices_hidden: bool}|null */
    #[ORM\Column(name: 'gift', type: 'json', nullable: true)]
    private ?array $gift = null;

    #[ORM\Column(name: 'wish_list', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $wishList = null;

    /** @var list<array{key: string, line: string|null, amount: int|null, currency: string|null, basis_points: int|null, reason: string, granted_by: string}> */
    #[ORM\Column(name: 'manual_discounts', type: 'json')]
    private array $manualDiscounts = [];

    #[ORM\Column(name: 'status', length: 64, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'revision', type: 'integer', options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(name: 'expired_at', nullable: true)]
    private ?DateTimeImmutable $expiredAt = null;

    /** How many payments were started for the order: each start moves the version on. */
    #[ORM\Column(name: 'payments_started', type: 'integer', options: ['default' => 0])]
    private int $paymentsStarted = 0;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'number', length: 64)]
        public readonly string $number,
        Currency $currency,
    ) {
        $this->currencyCode = $currency->code;
    }

    public static function open(string $number, Currency $currency): self
    {
        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $number)) {
            throw InvalidValue::because(\sprintf('"%s" is not an order number: 1 to 64 visible ASCII characters.', $number));
        }

        return new self($number, $currency);
    }

    /**
     * Adds a line, or adds the quantity to the line that already sells the same thing with the same option values, at
     * the new unit price. The sellable's options check the configuration: the same sellable with other values is
     * another line.
     *
     * @throws InvalidConfiguration when the sellable's options refuse the configuration
     */
    public function add(Sellable $sellable, int $quantity, Money $unitPrice, ?LineConfiguration $configuration = null): OrderLine
    {
        $this->checkCart();
        $this->checkCurrency($unitPrice);
        $line = OrderLine::of($sellable, $quantity, $unitPrice, $configuration);
        $existing = $this->lines[$line->reference->value()] ?? null;

        if (null !== $existing) {
            $line = $line->withQuantity($existing->quantity + $quantity);
        }

        return $this->lines[$line->reference->value()] = $line;
    }

    public function changeQuantity(SellableReference $reference, int $quantity, Money $unitPrice): void
    {
        $this->checkCart();
        $this->checkCurrency($unitPrice);
        $this->lines[$reference->value()] = $this->existingLine($reference)->withQuantity($quantity)->withUnitPrice($unitPrice);
    }

    /**
     * Removes a line, and the adjustments that were on it.
     */
    public function remove(SellableReference $reference): void
    {
        $this->checkCart();
        $this->existingLine($reference);
        unset($this->lines[$reference->value()]);

        $kept = [];

        foreach ($this->adjustments as $adjustment) {
            if (null === $adjustment->line || !$adjustment->line->equals($reference)) {
                $kept[] = $adjustment;
            }
        }

        $this->adjustments = $kept;
    }

    /**
     * Records the draws of one option of a line, one per unit, once the order is placed: the only change a placed
     * order's lines take, filling a value left to draw, never changing a chosen one. The line keeps its reference.
     *
     * @param list<string> $draws
     */
    public function recordDraws(SellableReference $reference, string $option, array $draws): void
    {
        if (OrderState::Placed !== $this->state) {
            throw OrderLocked::notPlaced($this->number, $this->state);
        }

        $this->lines[$reference->value()] = $this->existingLine($reference)->withDraws($option, $draws);
    }

    public function line(SellableReference $reference): ?OrderLine
    {
        return $this->lines[$reference->value()] ?? null;
    }

    /**
     * @return list<OrderLine> in the order they were first added
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->lines as $line) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * The zone the `TaxRateResolver` reads, as the application names it: `null` until the buyer's is known, and then
     * no tax is computed.
     */
    public function taxZone(): ?string
    {
        return $this->taxZone;
    }

    public function changeTaxZone(?string $zone): void
    {
        $this->checkCart();
        if ('' === $zone) {
            throw InvalidValue::because('A tax zone, when given, has a name.');
        }

        $this->taxZone = $zone;
    }

    /**
     * Whether the unit prices already include the taxes, as at a fair where the price on the tag is the price paid:
     * the taxes are then taken out of the prices, shown, and never added to the total.
     */
    public function taxIncluded(): bool
    {
        return $this->taxIncluded;
    }

    public function changeTaxIncluded(bool $included): void
    {
        $this->checkCart();
        $this->taxIncluded = $included;
    }

    /**
     * The channel that sells — a site, a point of sale —, as the application names it; `null` until known.
     */
    public function channel(): ?string
    {
        return $this->channel;
    }

    public function changeChannel(?string $channel): void
    {
        $this->checkCart();
        $this->channel = $this->checkedName($channel, 'channel');
    }

    /**
     * The buyer's customer group, as the application names it; `null` for a buyer of no group.
     */
    public function customerGroup(): ?string
    {
        return $this->customerGroup;
    }

    public function changeCustomerGroup(?string $customerGroup): void
    {
        $this->checkCart();
        $this->customerGroup = $this->checkedName($customerGroup, 'customer group');
    }

    /**
     * The delivery method the buyer chose, as the logistics module names it; `null` until chosen, or for nothing to
     * deliver.
     */
    public function deliveryMethod(): ?string
    {
        return $this->deliveryMethod;
    }

    public function chooseDeliveryMethod(?string $method): void
    {
        $this->checkCart();
        $this->deliveryMethod = self::checkedName($method, 'delivery method');
    }

    /**
     * @return list<string> the promotion codes entered, in the order entered
     */
    public function codes(): array
    {
        return $this->codes;
    }

    /**
     * Records a promotion code the buyer entered, once; whether it is worth anything is the promotions' decision.
     */
    public function enterCode(string $code): void
    {
        $this->checkCart();

        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $code)) {
            throw InvalidValue::because(\sprintf('"%s" is not a promotion code: 1 to 64 visible ASCII characters.', $code));
        }

        if (!\in_array($code, $this->codes, true)) {
            $this->codes[] = $code;
        }
    }

    public function removeCode(string $code): void
    {
        $this->checkCart();
        $this->codes = array_values(array_filter($this->codes, static fn (string $entered): bool => $entered !== $code));
    }

    /**
     * Replaces every adjustment of one type, as a processor does each time it runs.
     *
     * @param list<Adjustment> $adjustments
     */
    public function replaceAdjustments(AdjustmentType $type, array $adjustments): void
    {
        $this->checkCart();
        foreach ($adjustments as $adjustment) {
            if ($adjustment->type !== $type) {
                throw InvalidValue::because(\sprintf('A %s adjustment cannot replace the %s adjustments.', $adjustment->type->value, $type->value));
            }

            $this->checkCurrency($adjustment->amount);

            if (null !== $adjustment->line) {
                $this->existingLine($adjustment->line);
            }
        }

        $kept = [];

        foreach ($this->adjustments as $adjustment) {
            if ($adjustment->type !== $type) {
                $kept[] = $adjustment;
            }
        }

        $this->adjustments = [...$kept, ...$adjustments];
    }

    /**
     * @return list<Adjustment> every adjustment, or those of one type
     */
    public function adjustments(?AdjustmentType $type = null): array
    {
        $adjustments = [];

        foreach ($this->adjustments as $adjustment) {
            if (!$type instanceof AdjustmentType || $adjustment->type === $type) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @return list<Adjustment> the adjustments on one line, of every type or of one
     */
    public function lineAdjustments(SellableReference $reference, ?AdjustmentType $type = null): array
    {
        $adjustments = [];

        foreach ($this->adjustments($type) as $adjustment) {
            if (null !== $adjustment->line && $adjustment->line->equals($reference)) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * The adjustments on lines, of every type or of one, grouped by the line's reference value in one pass: what a
     * processor reads when it visits every line, instead of one `lineAdjustments()` per line.
     *
     * @return array<string, non-empty-list<Adjustment>> keyed by the reference's value; a line without adjustments is absent
     */
    public function adjustmentsByLine(?AdjustmentType $type = null): array
    {
        $byLine = [];

        foreach ($this->adjustments($type) as $adjustment) {
            if (null !== $adjustment->line) {
                $byLine[$adjustment->line->value()][] = $adjustment;
            }
        }

        return $byLine;
    }

    public function itemsTotal(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            $total = $total->add($line->subtotal());
        }

        return $total;
    }

    /**
     * The sum of the adjustments that are not neutral, of every type or of one.
     */
    public function adjustmentsTotal(?AdjustmentType $type = null): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->adjustments($type) as $adjustment) {
            if (!$adjustment->neutral) {
                $total = $total->add($adjustment->amount);
            }
        }

        return $total;
    }

    /**
     * The taxes the prices include: the neutral tax adjustments, zero when the taxes are added.
     */
    public function includedTax(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->adjustments(AdjustmentType::Tax) as $adjustment) {
            if ($adjustment->neutral) {
                $total = $total->add($adjustment->amount);
            }
        }

        return $total;
    }

    public function total(): Money
    {
        return $this->itemsTotal()->add($this->adjustmentsTotal());
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines;
    }

    public function state(): OrderState
    {
        return $this->state;
    }

    public function placedAt(): ?DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /**
     * The stored version, which each write moves on: a form or an API that changes an order carries it, so that a
     * change made over a stale copy is refused.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Counts a payment started for the order. The write moves the order's version on, so that two payments started
     * at once over the same copy of the order cannot both be committed: the second commit is refused as stale, and
     * neither can take a share of the order that the other one already holds.
     */
    public function countPaymentStart(): void
    {
        if (OrderState::Placed !== $this->state) {
            throw PaymentRefused::orderNotPlaced($this->number);
        }

        ++$this->paymentsStarted;
    }

    public function paymentsStarted(): int
    {
        return $this->paymentsStarted;
    }

    /**
     * The buyer agrees to the cart, or to the quote, as it stands: lines and adjustments are frozen. The application
     * recomputes a cart's adjustments first, which `OrderModifier::place()` does; a quote is placed as it was offered.
     */
    public function place(DateTimeImmutable $at): void
    {
        $this->moveTo(OrderState::Placed);

        if ($this->isEmpty()) {
            throw OrderLocked::empty($this->number);
        }

        $this->state = OrderState::Placed;
        $this->placedAt = $at;
        $this->status = null;
    }

    /**
     * Freezes the cart as a quote: the buyer accepts it as it stands, or it goes back to a cart.
     */
    public function quote(): void
    {
        $this->moveTo(OrderState::Quoted);

        if ($this->isEmpty()) {
            throw OrderLocked::empty($this->number);
        }

        $this->state = OrderState::Quoted;
        $this->status = null;
    }

    /**
     * Makes a quote or a placed order a cart again, to be changed then quoted or placed anew. Whether a placed order
     * may still change — nothing shipped, no receipt issued — is the application's to check, which
     * `OrderAmendments` does for receipts.
     */
    public function reopen(): void
    {
        if (OrderState::Cart === $this->state || OrderState::Cancelled === $this->state) {
            throw OrderLocked::transition($this->number, $this->state, OrderState::Cart);
        }

        $this->state = OrderState::Cart;
        $this->placedAt = null;
        $this->status = null;
    }

    /**
     * Gives the order up, as a cart, a quote or once placed. Refunding what was paid is the payment's affair.
     */
    public function cancel(DateTimeImmutable $at): void
    {
        $this->moveTo(OrderState::Cancelled);
        $this->state = OrderState::Cancelled;
        $this->cancelledAt = $at;
        $this->status = null;
    }

    /**
     * The customer who buys, by the stable identifier the application gives them; `null` for a guest.
     */
    public function customer(): ?string
    {
        return $this->customer;
    }

    public function assignCustomer(?string $customer): void
    {
        $this->checkCart();
        $this->customer = null === $customer ? null : Identifier::check($customer, 'customer');
    }

    /**
     * How the order is offered as a gift; `null` when it is not one.
     */
    public function giftOptions(): ?GiftOptions
    {
        return null === $this->gift ? null : GiftOptions::fromArray($this->gift);
    }

    /**
     * Offers the order as a gift, or no longer, with `null`; what the wrapping costs is the gift wrapping processor's.
     */
    public function offerAsGift(?GiftOptions $options): void
    {
        $this->checkCart();
        $this->gift = $options?->toArray();
    }

    /**
     * The wish list the order buys from, by its id; `null` for an order of no list.
     */
    public function wishList(): ?string
    {
        return $this->wishList;
    }

    public function buyFromWishList(?string $wishList): void
    {
        $this->checkCart();

        if (null !== $wishList && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $wishList)) {
            throw InvalidValue::because(\sprintf('"%s" is not the id of a wish list.', $wishList));
        }

        $this->wishList = $wishList;
    }

    /**
     * @return list<ManualDiscount> the discounts granted by hand, in the order granted
     */
    public function manualDiscounts(): array
    {
        return array_map(ManualDiscount::fromArray(...), $this->manualDiscounts);
    }

    /**
     * Grants a discount by hand, on a line of the order or on the whole order; a key already granted is refused.
     */
    public function grantManualDiscount(ManualDiscount $discount): void
    {
        $this->checkCart();

        if ($discount->amount instanceof Money) {
            $this->checkCurrency($discount->amount);
        }

        if ($discount->line instanceof SellableReference) {
            $this->existingLine($discount->line);
        }

        foreach ($this->manualDiscounts as $granted) {
            if ($granted['key'] === $discount->key) {
                throw InvalidValue::because(\sprintf('The order %s already has a manual discount "%s".', $this->number, $discount->key));
            }
        }

        $this->manualDiscounts[] = $discount->toArray();
    }

    public function withdrawManualDiscount(string $key): void
    {
        $this->checkCart();
        $this->manualDiscounts = array_values(array_filter($this->manualDiscounts, static fn (array $granted): bool => $granted['key'] !== $key));
    }

    /**
     * The application's own status of the order, attached to its state — "awaiting pickup" within placed —; `null`
     * for none. A move of the state clears it.
     */
    public function status(): ?string
    {
        return $this->status;
    }

    /**
     * Sets the application's status, attached to the state it belongs to: refused when the order is in another one.
     */
    public function changeStatus(?string $status, OrderState $attachedTo): void
    {
        if ($attachedTo !== $this->state) {
            throw OrderLocked::status($this->number, $this->state, (string) $status, $attachedTo);
        }

        $this->status = self::checkedName($status, 'status');
    }

    /**
     * Which version of the placed order this is: 1 when first placed, then one more for each change confirmed.
     */
    public function revision(): int
    {
        return $this->revision;
    }

    /**
     * Counts a confirmed change of the placed order, and returns the new revision.
     */
    public function recordRevision(): int
    {
        if (OrderState::Placed !== $this->state) {
            throw OrderLocked::notPlaced($this->number, $this->state);
        }

        return ++$this->revision;
    }

    /**
     * When the order expired unpaid; `null` when it did not.
     */
    public function expiredAt(): ?DateTimeImmutable
    {
        return $this->expiredAt;
    }

    /**
     * Cancels a placed order nobody paid in time, and remembers that it expired: an expired order is deleted once its
     * channel's retention ends.
     */
    public function expire(DateTimeImmutable $at): void
    {
        if (OrderState::Placed !== $this->state) {
            throw OrderLocked::transition($this->number, $this->state, OrderState::Cancelled);
        }

        $this->cancel($at);
        $this->expiredAt = $at;
    }

    /**
     * Moves the order to a state outside its transitions, as an employee allowed to force one does; whoever forces it
     * journals why. A placed order still has lines; moving back to a cart forgets when it was placed.
     */
    public function force(OrderState $to, DateTimeImmutable $at): void
    {
        if ($to === $this->state) {
            throw OrderLocked::transition($this->number, $this->state, $to);
        }

        if (OrderState::Placed === $to && $this->isEmpty()) {
            throw OrderLocked::empty($this->number);
        }

        if (OrderState::Cancelled === $to) {
            $this->cancelledAt = $at;
        } else {
            $this->placedAt = OrderState::Placed === $to ? $at : null;
            $this->cancelledAt = null;
            $this->expiredAt = null;
        }

        $this->state = $to;
        $this->status = null;
    }

    private function checkedName(?string $name, string $what): ?string
    {
        if (null !== $name && 1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not a %s: 1 to 64 visible ASCII characters.', $name, $what));
        }

        return $name;
    }

    private function checkCart(): void
    {
        if (OrderState::Cart !== $this->state) {
            throw OrderLocked::notACart($this->number, $this->state);
        }
    }

    private function moveTo(OrderState $to): void
    {
        $allowed = match ($this->state) {
            OrderState::Cart => [OrderState::Quoted, OrderState::Placed, OrderState::Cancelled],
            OrderState::Quoted => [OrderState::Placed, OrderState::Cancelled],
            OrderState::Placed => [OrderState::Cancelled],
            OrderState::Cancelled => [],
        };

        if (!\in_array($to, $allowed, true)) {
            throw OrderLocked::transition($this->number, $this->state, $to);
        }
    }

    private function existingLine(SellableReference $reference): OrderLine
    {
        return $this->lines[$reference->value()] ?? throw LineNotInOrder::of($this->number, $reference);
    }

    private function checkCurrency(Money $amount): void
    {
        if ($amount->currency->code !== $this->currencyCode) {
            throw CurrencyMismatch::between($this->currency, $amount->currency);
        }
    }
}
