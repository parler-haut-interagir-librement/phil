<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use ArrayObject;
use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\OptionDraws;
use Phil\CommerceBundle\Application\OptionSupplementProcessor;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OptionDrawer;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\Port\TaxRateResolver;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Exception\DrawRefused;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\TaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LineOptions::class)]
#[CoversClass(OptionSupplementProcessor::class)]
#[CoversClass(OptionDraws::class)]
#[CoversClass(OrderModifier::class)]
#[CoversClass(TaxOrderProcessor::class)]
#[CoversClass(DrawRefused::class)]
final class LineOptionsTest extends TestCase
{
    /** @var ArrayObject<int, string> */
    private ArrayObject $asked;

    /** @var ArrayObject<int, string> */
    private ArrayObject $pricesAsked;

    /** @var ArrayObject<int, string> */
    private ArrayObject $availabilityAsked;

    #[Override]
    protected function setUp(): void
    {
        $this->asked = new ArrayObject();
        $this->pricesAsked = new ArrayObject();
        $this->availabilityAsked = new ArrayObject();
    }

    public function testTheOptionsComeFromTheFirstCatalogueOfTheType(): void
    {
        $options = new LineOptions([$this->catalogue('offer'), $this->catalogue('product'), $this->catalogue('product')]);
        $reference = SellableReference::of('product', 'shirt', '0123456789abcdef');

        self::assertSame(['name', 'stone', 'glow', 'verse'], array_map(static fn (OptionDefinition $option): string => $option->code, $options->optionsOf($reference, Currency::of('CAD'))->options()));
        self::assertSame(['product:shirt CAD'], $this->asked->getArrayCopy());
        self::assertTrue($options->optionsOf(SellableReference::of('gift_card', 'a'), Currency::of('CAD'))->isEmpty());
        self::assertTrue(new LineOptions()->optionsOf($reference, Currency::of('CAD'))->isEmpty());
    }

    public function testTheModifierAddsConfiguredLinesAndChargesTheirOptionsBeforeTheTaxes(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->changeTaxZone('QC');

        $modifier = $this->modifier();

        $bob = $modifier->add($order, SellableReference::of('product', 'shirt'), 2, $this->context(), $this->configuration('Bob', 'marble', 'glow'));
        $alice = $modifier->add($order, SellableReference::of('product', 'shirt'), 1, $this->context(), $this->configuration('Alice'));
        $plain = $modifier->add($order, SellableReference::of('product', 'shirt'), 1, $this->context());

        self::assertCount(3, $order->lines());
        self::assertSame('Marble', $bob->configuration->value('stone')?->display);
        self::assertSame(['verse'], $bob->configuration->pending());
        self::assertTrue($plain->configuration->isEmpty());
        self::assertNull($plain->reference->configuration);

        $options = array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value(), $adjustment->origin], $order->adjustments(AdjustmentType::Option));
        self::assertSame([
            ['Name', 1_000, $bob->reference->value(), 'option:name'],
            ['Stone', 600, $bob->reference->value(), 'option:stone'],
            ['Ink', 800, $bob->reference->value(), 'option:glow'],
            ['Name', 500, $alice->reference->value(), 'option:name'],
        ], $options);

        // Taxed on 2 × 25.00 + 24.00 of options, at 10 %.
        self::assertSame(7_400 / 10, $order->lineAdjustments($bob->reference, AdjustmentType::Tax)[0]->amount->minorUnits);
        self::assertSame(3_000 / 10, $order->lineAdjustments($alice->reference, AdjustmentType::Tax)[0]->amount->minorUnits);
        self::assertSame(2_500 / 10, $order->lineAdjustments($plain->reference, AdjustmentType::Tax)[0]->amount->minorUnits);

        $more = $modifier->add($order, SellableReference::of('product', 'shirt'), 1, $this->context(), $this->configuration('Alice'));
        self::assertTrue($more->reference->equals($alice->reference));
        self::assertSame(2, $more->quantity);
        self::assertSame(1_000, $order->lineAdjustments($alice->reference, AdjustmentType::Option)[0]->amount->minorUnits);
        self::assertSame(['product:shirt 2', 'product:shirt 1', 'product:shirt 1', 'product:shirt 2'], $this->pricesAsked->getArrayCopy());

        $modifier->changeQuantity($order, $alice->reference, 3, $this->context());
        self::assertSame(3, $order->line($alice->reference)?->quantity);
        self::assertSame(1_500, $order->lineAdjustments($alice->reference, AdjustmentType::Option)[0]->amount->minorUnits);

        $modifier->changeAudience($order, 'store', null, $this->context());
        self::assertSame(['none', 'none', 'none', 'none', 'shop', 'shop', 'shop'], $this->availabilityAsked->getArrayCopy());
        self::assertSame(7_500, $order->line($alice->reference)?->subtotal()->minorUnits);
        self::assertCount(3, $order->lines());

        $modifier->remove($order, $bob->reference);
        self::assertCount(1, $order->adjustments(AdjustmentType::Option));
    }

    /**
     * Pay what you want: a sellable priced at zero, and the amount the buyer chose on the line, charged and taxed as an
     * option; another amount makes another line.
     */
    public function testTheAmountTheBuyerChoseIsChargedOnTheLine(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->changeTaxZone('QC');

        $modifier = $this->modifier();

        $twelve = $modifier->add($order, SellableReference::of('product', 'zine'), 2, $this->context(), LineConfiguration::of(['price' => OptionValue::chosen('1200')]));
        $five = $modifier->add($order, SellableReference::of('product', 'zine'), 1, $this->context(), LineConfiguration::of(['price' => OptionValue::chosen('500')]));

        self::assertCount(2, $order->lines());
        self::assertSame('12.00 CAD', $twelve->configuration->value('price')?->display);
        self::assertSame(0, $twelve->unitPrice->minorUnits);
        self::assertSame([['Your price', 2_400, 'option:price'], ['Your price', 500, 'option:price']], array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->origin], $order->adjustments(AdjustmentType::Option)));
        self::assertSame(240, $order->lineAdjustments($twelve->reference, AdjustmentType::Tax)[0]->amount->minorUnits);
        self::assertSame(50, $order->lineAdjustments($five->reference, AdjustmentType::Tax)[0]->amount->minorUnits);
    }

    public function testAnAmountOutOfItsRangeOrMissingIsRefused(): void
    {
        foreach ([['499', 'the amount is 5.00 or more'], ['5001', 'the amount is 50.00 or less'], ['12.00', '"12.00" is not an amount in minor units']] as [$amount, $reason]) {
            try {
                $this->modifier()->add(Order::open('A-1', Currency::of('CAD')), SellableReference::of('product', 'zine'), 1, $this->context(), LineConfiguration::of(['price' => OptionValue::chosen($amount)]));
                self::fail('The amount was taken.');
            } catch (InvalidConfiguration $invalidConfiguration) {
                self::assertSame(\sprintf('The value of the option price is refused: %s.', $reason), $invalidConfiguration->getMessage());
            }
        }

        $this->expectException(InvalidConfiguration::class);

        $this->modifier()->add(Order::open('A-1', Currency::of('CAD')), SellableReference::of('product', 'zine'), 1, $this->context());
    }

    public function testTheModifierRefusesAConfigurationTheOptionsRefuse(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('The value of the option stone is refused: "granite" is not one of its choices.');

        $this->modifier()->add($order, SellableReference::of('product', 'shirt'), 1, $this->context(), $this->configuration('Bob', 'granite'));
    }

    public function testALineWithoutConfigurationIsNotCharged(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'mug'), 'Mug', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->replaceAdjustments(AdjustmentType::Option, [Adjustment::of(AdjustmentType::Option, 'Old', $this->cad(1))]);

        new OptionSupplementProcessor(new LineOptions([$this->catalogue('product')]))->process($order);

        self::assertSame([], $order->adjustments(AdjustmentType::Option));
        self::assertSame([], $this->asked->getArrayCopy());
    }

    public function testAConfiguredLineAfterOneWithoutConfigurationIsCharged(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $modifier = $this->modifier();
        $modifier->add($order, SellableReference::of('product', 'mug'), 1, $this->context());
        $modifier->add($order, SellableReference::of('product', 'shirt'), 1, $this->context(), $this->configuration('Bob'));

        self::assertSame(['Name'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->adjustments(AdjustmentType::Option)));
    }

    public function testThePaidOrderIsDrawnOncePerUnitAndOnce(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $modifier = $this->modifier();
        $loot = $modifier->add($order, SellableReference::of('product', 'loot'), 2, $this->context());
        $shirt = $modifier->add($order, SellableReference::of('product', 'shirt'), 1, $this->context(), $this->configuration('Bob'));
        $modifier->add($order, SellableReference::of('product', 'mug'), 1, $this->context());
        $modifier->place($order, new DateTimeImmutable('2026-11-07'));

        $draws = new OptionDraws([$this->drawer('other'), $this->drawer('dice'), $this->drawer('verses'), $this->drawer('dice')]);

        try {
            $draws->draw($order, OrderPayments::of($order, []));
            self::fail('An unpaid order was drawn.');
        } catch (DrawRefused $drawRefused) {
            self::assertSame('The order A-1 is unpaid: its options are drawn once it is paid.', $drawRefused->getMessage());
        }

        $paid = $this->paid($order);
        self::assertSame(3, $draws->draw($order, $paid));
        self::assertSame(['dice:1@A-1/'.$loot->reference->value().'#dice', 'dice:2@A-1/'.$loot->reference->value().'#dice'], $order->line($loot->reference)?->configuration->value('dice')?->draws);
        $extra = $order->line($loot->reference)->configuration->value('extra');
        self::assertNotNull($extra);
        self::assertSame(['dice:1@A-1/'.$loot->reference->value().'#extra', 'dice:2@A-1/'.$loot->reference->value().'#extra'], $extra->draws);
        self::assertSame(['verses:1@A-1/'.$shirt->reference->value().'#verse'], $order->line($shirt->reference)?->configuration->value('verse')?->draws);
        self::assertSame(0, $draws->draw($order, $paid));
    }

    public function testADrawWithoutItsDrawerIsRefused(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $this->modifier()->add($order, SellableReference::of('product', 'loot'), 1, $this->context());
        $order->place(new DateTimeImmutable('2026-11-07'));

        $this->expectException(DrawRefused::class);
        $this->expectExceptionMessageIsOrContains('No option drawer supports the drawer "dice".');

        new OptionDraws([$this->drawer('verses')])->draw($order, $this->paid($order));
    }

    public function testNoDrawerAtAllRefusesTheDraw(): void
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $this->modifier()->add($order, SellableReference::of('product', 'loot'), 1, $this->context());
        $order->place(new DateTimeImmutable('2026-11-07'));

        $this->expectException(DrawRefused::class);

        new OptionDraws()->draw($order, $this->paid($order));
    }

    private function modifier(): OrderModifier
    {
        $sellables = new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'product' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): Sellable
            {
                return Sellable::of($reference, ucfirst($reference->id), Money::of('zine' === $reference->id ? 0 : 2_500, Currency::of('CAD')), 'goods');
            }
        };
        $prices = new readonly class($this->pricesAsked) implements UnitPriceResolver {
            /** @param ArrayObject<int, string> $asked */
            public function __construct(private ArrayObject $asked)
            {
            }

            #[Override]
            public function priceFor(Sellable $sellable, PricingContext $context): ?Money
            {
                $this->asked->append($sellable->reference->value().' '.$context->quantity);

                return null;
            }
        };
        $availability = new readonly class($this->availabilityAsked) implements SellableAvailability {
            /** @param ArrayObject<int, string> $asked */
            public function __construct(private ArrayObject $asked)
            {
            }

            #[Override]
            public function offerThrough(SellableReference $reference, ?string $channel): Offer
            {
                if (null !== $reference->configuration) {
                    return Offer::hidden();
                }

                $this->asked->append(null === $channel ? 'none' : 'shop');

                return Offer::full();
            }
        };
        $taxes = new class implements TaxRateResolver {
            #[Override]
            public function ratesFor(string $taxCategory, string $zone): array
            {
                return [TaxRate::ofPercent('TAX', '10')];
            }
        };
        $options = new LineOptions([$this->catalogue('product')]);

        return new OrderModifier([$sellables], [$prices], [new OptionSupplementProcessor($options), new TaxOrderProcessor($taxes)], [$availability], $options);
    }

    private function catalogue(string $type): SellableOptions
    {
        return new readonly class($type, $this->asked) implements SellableOptions {
            /** @param ArrayObject<int, string> $asked */
            public function __construct(private string $type, private ArrayObject $asked)
            {
            }

            #[Override]
            public function supports(string $type): bool
            {
                return $this->type === $type;
            }

            #[Override]
            public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
            {
                $this->asked->append($reference->value().' '.$currency->code);
                $cad = static fn (int $minor): Money => Money::of($minor, $currency);

                return match ($reference->id) {
                    'shirt' => OptionSet::of(
                        OptionDefinition::text('name', 'Name', TextConstraints::of(12), supplement: $cad(500)),
                        OptionDefinition::choice('stone', 'Stone', [OptionChoice::of('plain', 'Plain'), OptionChoice::of('marble', 'Marble', 'marble.png', $cad(300))]),
                        OptionDefinition::ink('glow', 'Ink', [OptionChoice::of('glow', 'Glowing', supplement: $cad(400))]),
                        OptionDefinition::text('verse', 'Verse', TextConstraints::of(40, sources: [TextSource::Drawn], entries: ['One', 'Two']), drawer: 'verses'),
                    ),
                    'loot' => OptionSet::of(OptionDefinition::draw('dice', 'Dice', 'dice'), OptionDefinition::draw('extra', 'Extra', 'dice')),
                    'zine' => OptionSet::of(OptionDefinition::amount('price', 'Your price', AmountRange::of($cad(500), $cad(5_000)))),
                    default => OptionSet::none(),
                };
            }
        };
    }

    private function drawer(string $name): OptionDrawer
    {
        return new readonly class($name) implements OptionDrawer {
            public function __construct(private string $name)
            {
            }

            #[Override]
            public function supports(string $drawer): bool
            {
                return $this->name === $drawer;
            }

            #[Override]
            public function draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string
            {
                return \sprintf('%s:%d@%s/%s#%s', $drawer, $unit, $order->number, $line->reference->value(), $option);
            }
        };
    }

    private function configuration(string $name, ?string $stone = null, ?string $ink = null): LineConfiguration
    {
        return LineConfiguration::of(array_filter([
            'name' => OptionValue::chosen($name),
            'stone' => null === $stone ? null : OptionValue::chosen($stone),
            'glow' => null === $ink ? null : OptionValue::chosen($ink),
            'verse' => OptionValue::toDraw(),
        ]));
    }

    private function paid(Order $order): OrderPayments
    {
        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable());
        $payment->apply(PaymentNotification::of('evt_1', 'pi', PaymentEvent::Captured));

        return OrderPayments::of($order, [$payment]);
    }

    private function context(): PricingContext
    {
        return PricingContext::of(1, new DateTimeImmutable('2026-11-07T12:00:00Z'));
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
