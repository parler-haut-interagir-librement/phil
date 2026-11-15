<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * The gift wrapping of `phil_commerce.gift_wrapping`: whether the store wraps at all, whether it offers the recycled
 * wrap, and what each costs per currency. As an order processor, it charges one wrapping per wrapped order as a
 * `Fee` adjustment of origin `gift_wrap` or `gift_wrap:recycled`, and leaves the other fees alone.
 *
 * Like any adjustment on the whole order, the wrapping is not taxed by `TaxOrderProcessor`, which taxes lines; a
 * store whose wrapping is taxed sells it as a sellable instead.
 */
final readonly class GiftWrapping implements OrderProcessor
{
    public const string ORIGIN = 'gift_wrap';

    public const string RECYCLED_ORIGIN = 'gift_wrap:recycled';

    /**
     * @param array<string, string> $prices         currency code → the price of a wrapping, as a decimal
     * @param array<string, string> $recycledPrices currency code → the price of a recycled wrapping; the plain price when absent
     */
    public function __construct(
        private bool $enabled = false,
        private bool $recycledOffered = true,
        private array $prices = [],
        private array $recycledPrices = [],
        private string $label = 'Gift wrapping',
    ) {
    }

    /**
     * Refuses the options the store does not offer: a wrapping when it does not wrap, a recycled wrap when it does not
     * offer one. A message and hidden prices are always offered.
     */
    public function check(GiftOptions $options): void
    {
        if ($options->wrapped && !$this->enabled) {
            throw InvalidValue::because('This store does not wrap gifts.');
        }

        if ($options->recycledWrap && !$this->recycledOffered) {
            throw InvalidValue::because('This store does not offer a recycled wrap.');
        }
    }

    #[Override]
    public function process(Order $order): void
    {
        $fees = [];

        foreach ($order->adjustments(AdjustmentType::Fee) as $fee) {
            if (self::ORIGIN !== $fee->origin && self::RECYCLED_ORIGIN !== $fee->origin) {
                $fees[] = $fee;
            }
        }

        $options = $order->giftOptions();

        if ($this->enabled && $options instanceof GiftOptions && $options->wrapped) {
            $code = $order->currency->code;
            $price = $options->recycledWrap ? ($this->recycledPrices[$code] ?? $this->prices[$code] ?? '0') : ($this->prices[$code] ?? '0');
            $fees[] = Adjustment::of(AdjustmentType::Fee, $this->label, Money::ofDecimal($price, $order->currency), origin: $options->recycledWrap ? self::RECYCLED_ORIGIN : self::ORIGIN);
        }

        $order->replaceAdjustments(AdjustmentType::Fee, $fees);
    }
}
