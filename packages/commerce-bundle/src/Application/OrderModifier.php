<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Exception\NotSoldThere;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Where the modules meet the order: each change asks the sellable resolvers what is sold and the catalogues its
 * options, the channels' selections whether the order's channel sells it, the unit price resolvers at what price,
 * then runs every order processor, so that an order always leaves with its adjustments recomputed.
 *
 * **Prices come from the resolvers, never from the caller**: a form or a request names what to buy and how many,
 * and the server decides what it costs.
 */
final readonly class OrderModifier
{
    /**
     * @param iterable<SellableResolver>     $sellables
     * @param iterable<UnitPriceResolver>    $prices         in the order of their tags
     * @param iterable<OrderProcessor>       $processors     in the order of their tags
     * @param iterable<SellableAvailability> $availabilities the channels' selections: a line is refused unless every one lets an order hold it
     * @param LineOptions                    $options        the options the catalogues declare
     * @param GiftWrapping|null              $giftWrapping   what the store wraps; without it, no wrapping is refused
     * @param OrderLifecycle|null            $lifecycle      the workflow that places a cart; without it, the order places itself
     */
    public function __construct(
        private iterable $sellables,
        private iterable $prices,
        private iterable $processors,
        private iterable $availabilities = [],
        private LineOptions $options = new LineOptions(),
        private ?GiftWrapping $giftWrapping = null,
        private ?OrderLifecycle $lifecycle = null,
    ) {
    }

    /**
     * Adds the sellable with its option values, checked by the options its catalogue declares: the same values as a
     * line already in the order add to it, other values make another line.
     *
     * @throws InvalidConfiguration when the options refuse the configuration
     */
    public function add(Order $order, SellableReference $reference, int $quantity, PricingContext $context, ?LineConfiguration $configuration = null): OrderLine
    {
        $sellable = $this->sellable($reference, $order->currency);
        $this->checkSoldThrough($reference, $order->channel());
        $configuration = $sellable->options->check($configuration ?? LineConfiguration::none());
        $line = $order->line($sellable->reference->configuredBy($configuration));
        $total = $line instanceof OrderLine ? $line->quantity + $quantity : $quantity;

        $added = $order->add($sellable, $quantity, $this->unitPrice($sellable, $this->context($order, $total, $context)), $configuration);
        $this->recalculate($order);

        return $added;
    }

    public function changeQuantity(Order $order, SellableReference $reference, int $quantity, PricingContext $context): void
    {
        $sellable = $this->sellable($reference, $order->currency);

        $order->changeQuantity($reference, $quantity, $this->unitPrice($sellable, $this->context($order, $quantity, $context)));
        $this->recalculate($order);
    }

    public function remove(Order $order, SellableReference $reference): void
    {
        $order->remove($reference);
        $this->recalculate($order);
    }

    public function changeTaxZone(Order $order, ?string $zone): void
    {
        $order->changeTaxZone($zone);
        $this->recalculate($order);
    }

    /**
     * Sells at prices that include the taxes, or that do not, then recomputes: the same unit prices, the taxes taken
     * out of them or added to them.
     */
    public function changeTaxIncluded(Order $order, bool $included): void
    {
        $order->changeTaxIncluded($included);
        $this->recalculate($order);
    }

    /**
     * Sets the channel and the customer group the order is sold through and to, then prices every line again for
     * them and recomputes.
     */
    public function changeAudience(Order $order, ?string $channel, ?string $customerGroup, PricingContext $context): void
    {
        foreach ($order->lines() as $line) {
            $this->checkSoldThrough($line->reference, $channel);
        }

        $order->changeChannel($channel);
        $order->changeCustomerGroup($customerGroup);

        foreach ($order->lines() as $line) {
            $order->changeQuantity($line->reference, $line->quantity, $this->unitPrice($this->sellable($line->reference, $order->currency), $this->context($order, $line->quantity, $context)));
        }

        $this->recalculate($order);
    }

    public function chooseDeliveryMethod(Order $order, ?string $method): void
    {
        $order->chooseDeliveryMethod($method);
        $this->recalculate($order);
    }

    public function enterCode(Order $order, string $code): void
    {
        $order->enterCode($code);
        $this->recalculate($order);
    }

    public function removeCode(Order $order, string $code): void
    {
        $order->removeCode($code);
        $this->recalculate($order);
    }

    /**
     * Offers the order as a gift, or no longer, with `null`, then recomputes, which charges the wrapping; options the
     * store does not offer are refused.
     */
    public function offerAsGift(Order $order, ?GiftOptions $options): void
    {
        if ($options instanceof GiftOptions) {
            $this->giftWrapping?->check($options);
        }

        $order->offerAsGift($options);
        $this->recalculate($order);
    }

    /**
     * Grants a discount by hand, then recomputes, which takes it off before the taxes.
     */
    public function grantManualDiscount(Order $order, ManualDiscount $discount): void
    {
        $order->grantManualDiscount($discount);
        $this->recalculate($order);
    }

    public function withdrawManualDiscount(Order $order, string $key): void
    {
        $order->withdrawManualDiscount($key);
        $this->recalculate($order);
    }

    /**
     * Recomputes the cart one last time, then places it: what the buyer agrees to is what the processors give now,
     * not what they gave when the cart was last changed. With the order workflow, the cart is placed through it, its
     * guards and its events.
     *
     * @param string|null $actor who places it, for the journal
     */
    public function place(Order $order, DateTimeImmutable $at, ?string $actor = null): void
    {
        $this->recalculate($order);

        if ($this->lifecycle instanceof OrderLifecycle) {
            $this->lifecycle->place($order, $at, $actor);

            return;
        }

        $order->place($at);
    }

    /**
     * Runs every processor again, after a change the modifier did not make: a promotion code applied, a delivery
     * method chosen.
     */
    public function recalculate(Order $order): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($order);
        }
    }

    /**
     * What the reference sells, asked without a line's configuration, with the options its catalogue declares.
     */
    private function sellable(SellableReference $reference, Currency $currency): Sellable
    {
        $reference = $reference->unconfigured();

        foreach ($this->sellables as $resolver) {
            if ($resolver->supports($reference->type)) {
                $sellable = $resolver->resolve($reference) ?? throw UnknownSellable::of($reference);

                return $sellable->withOptions($this->options->optionsOf($reference, $currency));
            }
        }

        throw UnknownSellable::of($reference);
    }

    private function checkSoldThrough(SellableReference $reference, ?string $channel): void
    {
        if (!new SellableOffers($this->availabilities)->of($reference, $channel)->orderable) {
            throw NotSoldThere::of($reference->unconfigured(), $channel);
        }
    }

    /**
     * The context a line is priced in: its whole quantity, and the order's channel and customer group unless the caller
     * gives its own.
     */
    private function context(Order $order, int $quantity, PricingContext $context): PricingContext
    {
        return PricingContext::of($quantity, $context->at, $context->customerGroup ?? $order->customerGroup(), $context->channel ?? $order->channel());
    }

    private function unitPrice(Sellable $sellable, PricingContext $context): Money
    {
        foreach ($this->prices as $resolver) {
            $price = $resolver->priceFor($sellable, $context);

            if (null !== $price) {
                return $price;
            }
        }

        return $sellable->unitPrice;
    }
}
