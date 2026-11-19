<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Application\CashDifferenceReport;
use Phil\CommercePosBundle\Application\CashDifferences;
use Phil\CommercePosBundle\Application\Contract\TillDrawForm;
use Phil\CommercePosBundle\Application\CustomerDisplay;
use Phil\CommercePosBundle\Application\DisplayedCart;
use Phil\CommercePosBundle\Application\PendingDraw;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\SalePresets;
use Phil\CommercePosBundle\Application\TillDraws;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the counter adds to a sale: the presets, the customer display, the draws entered by hand, a seller's sale
 * finished at the main register, tickets, and the cash-difference report.
 */
#[CoversClass(SalePresets::class)]
#[CoversClass(SalePreset::class)]
#[CoversClass(CustomerDisplay::class)]
#[CoversClass(DisplayedCart::class)]
#[CoversClass(TillDraws::class)]
#[CoversClass(PendingDraw::class)]
#[CoversClass(CashDifferences::class)]
#[CoversClass(CashDifferenceReport::class)]
#[CoversClass(RegisterCheckout::class)]
final class CounterTest extends TestCase
{
    private Till $till;

    private DateTimeImmutable $at;

    private CashSession $session;

    #[Override]
    protected function setUp(): void
    {
        $this->till = new Till();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
    }

    public function testAPresetSellsThroughItsChannelToItsGroup(): void
    {
        $member = $this->till->presets->create('member', 'Member', 'shop', 'members', identifiesCustomer: true);
        $this->till->presets->create('con', 'Convention', null);
        $this->till->registers->get('front')->place('store-1', 'counter');
        $order = $this->till->cart(['TEA' => 2]);

        self::assertSame($member, $this->till->presets->apply($order, 'member', 'front', $this->at));
        self::assertSame(['shop', 'members', 998], [$order->channel(), $order->customerGroup(), $order->total()->minorUnits]);

        $this->till->presets->apply($order, 'con', 'front', $this->at);
        self::assertSame(['counter', null], [$order->channel(), $order->customerGroup()]);
        self::assertSame(['con', 'member'], array_map(static fn (SalePreset $preset): string => $preset->code, $this->till->presets->active()));

        $member->retire();
        self::assertSame(['con'], array_map(static fn (SalePreset $preset): string => $preset->code, $this->till->presets->active()));

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('No active sale preset is known as "member".');

        $this->till->presets->get('member');
    }

    public function testAPresetThatIdentifiesTheCustomerRingsUpOnceTheCartHasOne(): void
    {
        $member = $this->till->presets->create('member', 'Member', null, null, true);
        $order = $this->till->cart(['TEA' => 1]);

        try {
            $this->till->checkout->ring($order, $this->session, 'ada', $this->at, $member);
            self::fail('A member sale was rung up without its member.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The order A-1 is sold under a preset that identifies the customer: assign one first.', $posRefused->getMessage());
        }

        $order->assignCustomer('customer-9');
        self::assertSame('member', $this->till->checkout->ring($order, $this->session, 'ada', $this->at, $member)->preset);

        $other = $this->till->cart(['TEA' => 1], 'A-2');
        self::assertSame('con', $this->till->checkout->ring($other, $this->session, 'ada', $this->at, $this->till->presets->create('con', 'Convention', null))->preset);
        self::assertNull($this->till->checkout->ring($this->till->cart(['TEA' => 1], 'A-3'), $this->session, 'ada', $this->at)->preset);
    }

    public function testAPresetIsChecked(): void
    {
        foreach ([
            static fn (): SalePreset => SalePreset::of('Member', 'Member', null),
            static fn (): SalePreset => SalePreset::of('member', ' ', null),
            static fn (): SalePreset => SalePreset::of('member', str_repeat('é', 65), null),
            static fn (): SalePreset => SalePreset::of('member', 'Member', 'a b'),
            static fn (): SalePreset => SalePreset::of('member', 'Member', null, str_repeat('g', 65)),
        ] as $index => $attempt) {
            try {
                $attempt();
                self::fail((string) $index);
            } catch (InvalidValue $invalid) {
                self::assertNotSame('', $invalid->getMessage());
            }
        }

        $preset = SalePreset::of('member', str_repeat('é', 64), null);
        $preset->change('Members', 'shop', 'members', true);
        self::assertSame(['Members', 'shop', 'members', true, true], [$preset->label(), $preset->channel(), $preset->customerGroup(), $preset->identifiesCustomer(), $preset->isActive()]);
        $preset->retire();
        self::assertFalse($preset->isActive());
        $preset->reinstate();
        self::assertTrue($preset->isActive());
    }

    public function testTheCustomerDisplayShowsTheCartAndWhatIsLeftButNobody(): void
    {
        $order = $this->till->cart(['TEA' => 2, 'MUG' => 1]);
        $order->assignCustomer('customer-9');

        $this->till->checkout->tip($order, $this->cad(150));

        $cart = $this->till->display->show($order);

        self::assertSame(['A-1', 2_198, 0, 0, false, 150, 2_348, 0, 2_348], [$cart->orderNumber, $cart->items->minorUnits, $cart->discounts->minorUnits, $cart->tax->minorUnits, $cart->taxIncluded, $cart->tip->minorUnits, $cart->total->minorUnits, $cart->paid->minorUnits, $cart->left->minorUnits]);
        self::assertSame([
            'order' => 'A-1',
            'lines' => [
                ['description' => 'Product TEA', 'quantity' => 2, 'unit_price' => ['amount' => 499, 'currency' => 'CAD'], 'subtotal' => ['amount' => 998, 'currency' => 'CAD']],
                ['description' => 'Product MUG', 'quantity' => 1, 'unit_price' => ['amount' => 1_200, 'currency' => 'CAD'], 'subtotal' => ['amount' => 1_200, 'currency' => 'CAD']],
            ],
            'items' => ['amount' => 2_198, 'currency' => 'CAD'],
            'discounts' => ['amount' => 0, 'currency' => 'CAD'],
            'tax' => ['amount' => 0, 'currency' => 'CAD'],
            'tax_included' => false,
            'tip' => ['amount' => 150, 'currency' => 'CAD'],
            'total' => ['amount' => 2_348, 'currency' => 'CAD'],
            'paid' => ['amount' => 0, 'currency' => 'CAD'],
            'left' => ['amount' => 2_348, 'currency' => 'CAD'],
        ], $cart->toArray());
        self::assertStringNotContainsString('customer-9', json_encode($cart->toArray(), \JSON_THROW_ON_ERROR));

        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(1_000), 'A1', $this->at);

        $paid = $this->till->display->show($order);
        self::assertSame([1_000, 1_348], [$paid->paid->minorUnits, $paid->left->minorUnits]);
    }

    public function testTheDiceOfALootBoxAreEnteredAtTheCounterOncePaid(): void
    {
        $draws = new TillDraws($this->till->payments, [$this->diceForm()]);
        $order = $this->till->cart(['LOOT' => 2, 'TEA' => 1]);
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);

        $pending = $draws->pending($order);
        self::assertCount(1, $pending);
        $loot = $pending[0]->reference;
        self::assertSame([$order->lines()[0]->reference->value(), 'Product LOOT', 'dice', 'Dice', 'loot-dice', 2, '@LootTables/till/dice.html.twig'], [$pending[0]->reference->value(), $pending[0]->description, $pending[0]->option, $pending[0]->label, $pending[0]->drawer, $pending[0]->units, $pending[0]->template]);

        try {
            $draws->record($order, $loot, 'dice', [['dice' => '3,4'], ['dice' => '6,6']]);
            self::fail('Dice were entered before the order was paid.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The order A-1 is not paid: its dice are rolled once it is.', $posRefused->getMessage());
        }

        $this->till->registerPayments->recorded($order, $this->session, 'debit', $order->total(), 'A1', $this->at);

        foreach ([
            [[['dice' => '3,4']], 'The line '.$loot->value().' takes 2 draws; 1 were entered.'],
            [[['dice' => '3,4'], ['dice' => '9']], 'Not a roll of 1d6+1d6: 9.'],
            [[['dice' => '3,4'], []], 'Not a roll of 1d6+1d6: .'],
        ] as [$entered, $message]) {
            try {
                $draws->record($order, $loot, 'dice', $entered);
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame(['3 + 4', '6 + 6'], $draws->record($order, $loot, 'dice', [['dice' => '3,4'], ['dice' => '6,6']]));
        self::assertSame('3 + 4, 6 + 6', $order->line($loot)?->configuration->value('dice')?->shown());
        self::assertSame([], $draws->pending($order));

        foreach ([
            [$loot, 'dice', 'The option dice of the line '.$loot->value().' has nothing left to draw.'],
            [$loot, 'colour', 'The option colour of the line '.$loot->value().' has nothing left to draw.'],
            [SellableReference::of('product', 'TEA'), 'dice', 'The option dice of the line product:TEA has nothing left to draw.'],
            [SellableReference::of('product', 'MUG'), 'dice', 'The order A-1 has no line product:MUG.'],
        ] as [$reference, $option, $message]) {
            try {
                $draws->record($order, $reference, $option, [['dice' => '1,1'], ['dice' => '1,1']]);
                self::fail($message);
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testEveryLineLeftToDrawIsListedAndEachUnitReadWithItsNumber(): void
    {
        $form = $this->diceForm();
        $draws = new TillDraws($this->till->payments, [$form]);
        $order = $this->till->cart(['LOOT' => 1, 'CHEST' => 3]);
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $order->total(), 'A1', $this->at);

        $pending = $draws->pending($order);
        self::assertSame([['Product LOOT', 1], ['Product CHEST', 3]], array_map(static fn (PendingDraw $draw): array => [$draw->description, $draw->units], $pending));

        $draws->record($order, $pending[1]->reference, 'dice', [['dice' => '1,2'], ['dice' => '3,4'], ['dice' => '5,6']]);

        self::assertSame([1, 2, 3], $form->units);
        self::assertSame(['Product LOOT'], array_map(static fn (PendingDraw $draw): string => $draw->description, $draws->pending($order)));
    }

    public function testADrawNoFormEntersIsLeftToTheCore(): void
    {
        $draws = new TillDraws($this->till->payments);
        $order = $this->till->cart(['LOOT' => 1]);
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $order->total(), 'A1', $this->at);

        self::assertSame([], $draws->pending($order));

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('No form at the register enters the draws of "loot-dice".');

        $draws->record($order, $order->lines()[0]->reference, 'dice', [['dice' => '1,2']]);
    }

    public function testASellersSaleIsFinishedAtTheMainRegisterOfItsLocation(): void
    {
        $this->till->registers->create('phone-1', "Ada's phone", Currency::of('CAD'), 5, 'store-1');
        $this->till->registers->create('phone-2', "Bob's phone", Currency::of('CAD'), 5, 'store-1');
        $this->till->registers->create('stand', 'Market stand', Currency::of('CAD'), 5, 'market');

        $order = $this->till->cart(['TEA' => 1]);
        $this->till->checkout->hold($order, 'phone-1', 'Red coat', 'ada', $this->at);

        foreach (['phone-2', 'stand'] as $register) {
            try {
                $this->till->checkout->resume('A-1', $register);
                self::fail($register);
            } catch (PosRefused $refused) {
                self::assertSame('The sale A-1 is not picked up at '.$register.': at the register that held it, or at the main register of its location.', $refused->getMessage());
            }
        }

        self::assertSame($order, $this->till->checkout->resume('A-1', 'front'));

        $this->till->checkout->hold($order, 'front', 'Red coat', 'ada', $this->at);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The sale A-1 is not picked up at phone-1');

        $this->till->checkout->resume('A-1', 'phone-1');
    }

    public function testATicketOfTheCoreIsSoldLikeAnyOtherSellable(): void
    {
        $order = $this->till->cart([]);
        $this->till->modifier->add($order, SellableReference::of('ticket', 'GALA'), 2, PricingContext::of(2, $this->at));
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);

        $tender = $this->till->registerPayments->cash($order, $this->session, $this->cad(5_000), 'ada', $this->at);

        self::assertSame([5_000, 0], [$tender->due->minorUnits, $tender->change->minorUnits]);
        self::assertSame(1, $this->till->sessions->report($this->session->id)->sales);
    }

    public function testTheCashDifferenceReportListsTheClosedSessionsAndFlagsTheLargeOnes(): void
    {
        $this->till->registers->create('back', 'Back', Currency::of('CAD'), 5, 'store-1');
        $this->till->sessions->close($this->session->id, $this->cad(-0 + 50), 'ada', $this->at->modify('+8 hours'));

        $back = $this->till->sessions->open('back', 'bob', $this->cad(1_000), $this->at);
        $this->till->sessions->close($back->id, $this->cad(990), 'bob', $this->at->modify('+9 hours'));
        $late = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at->modify('+1 day'));
        $this->till->sessions->close($late->id, $this->cad(0), 'ada', $this->at->modify('+1 day 8 hours'));
        $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at->modify('+2 days'));

        $report = new CashDifferences($this->till->pos, 20)->between($this->at, $this->at->modify('+1 day'));

        self::assertSame([[$this->session->id, 50, true], [$back->id, -10, false]], array_map(static fn (array $row): array => [$row['session']->id, $row['difference']->minorUnits, $row['flagged']], $report->sessions));
        self::assertSame([['CAD' => 50], ['CAD' => 10]], [array_map(static fn (Money $amount): int => $amount->minorUnits, $report->over), array_map(static fn (Money $amount): int => $amount->minorUnits, $report->short)]);
        self::assertSame([$this->session], $report->flagged());
        self::assertSame([$this->at, null], [$report->from, $report->register]);

        $back = new CashDifferences($this->till->pos)->between($this->at, $this->at->modify('+3 days'), 'front');
        self::assertSame([[50, false], [0, false]], array_map(static fn (array $row): array => [$row['difference']->minorUnits, $row['flagged']], $back->sessions));
        self::assertSame([[], 'front'], [$back->flagged(), $back->register]);

        $edge = new CashDifferences($this->till->pos, 50)->between($this->at, $this->at->modify('+8 hours 1 second'));
        self::assertSame([false], array_map(static fn (array $row): bool => $row['flagged'], $edge->sessions));
        self::assertSame([], new CashDifferences($this->till->pos)->between($this->at, $this->at->modify('+8 hours'))->sessions);

        foreach ([
            fn (): CashDifferenceReport => new CashDifferences($this->till->pos)->between($this->at, $this->at),
            fn (): CashDifferences => new CashDifferences($this->till->pos, -1),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('A report took a period that ends before it starts, or a negative threshold.');
            } catch (InvalidValue $invalid) {
                self::assertContains($invalid->getMessage(), ['A cash-difference report ends after it starts.', 'A cash-difference threshold is zero or more.']);
            }
        }
    }

    public function testTheDifferencesAddUpPerCurrencyInTheOrderOfTheirCodes(): void
    {
        $this->till->registers->create('us', 'US', Currency::of('USD'));
        $this->till->registers->create('back', 'Back', Currency::of('CAD'));

        $us = $this->till->sessions->open('us', 'ada', Money::of(1_000, Currency::of('USD')), $this->at);
        $this->till->sessions->close($us->id, Money::of(995, Currency::of('USD')), 'ada', $this->at->modify('+1 hour'));
        $first = $this->till->sessions->open('back', 'bob', $this->cad(1_000), $this->at);
        $this->till->sessions->close($first->id, $this->cad(990), 'bob', $this->at->modify('+2 hours'));
        $second = $this->till->sessions->open('back', 'bob', $this->cad(1_000), $this->at->modify('+3 hours'));
        $this->till->sessions->close($second->id, $this->cad(997), 'bob', $this->at->modify('+4 hours'));

        $report = new CashDifferences($this->till->pos, 0)->between($this->at, $this->at->modify('+1 day'));

        self::assertSame([$us, $first, $second], $report->flagged());
        self::assertSame(['CAD' => 13, 'USD' => 5], array_map(static fn (Money $amount): int => $amount->minorUnits, $report->short));
        self::assertSame(['CAD' => 0, 'USD' => 0], array_map(static fn (Money $amount): int => $amount->minorUnits, $report->over));
    }

    /**
     * @return TillDrawForm&object{units: list<int>}
     */
    private function diceForm(): TillDrawForm
    {
        return new class implements TillDrawForm {
            /** @var list<int> the number of each unit read, in order */
            public array $units = [];

            #[Override]
            public function supports(string $drawer): bool
            {
                return 'loot-dice' === $drawer;
            }

            #[Override]
            public function template(string $drawer): string
            {
                return '@LootTables/till/dice.html.twig';
            }

            #[Override]
            public function read(string $drawer, array $entered, OrderLine $line, string $option, int $unit): string
            {
                $this->units[] = $unit;
                $dice = \is_string($entered['dice'] ?? null) ? $entered['dice'] : '';

                if (1 !== preg_match('/^([1-6]),([1-6])$/D', $dice, $faces)) {
                    throw InvalidValue::because('Not a roll of 1d6+1d6: '.$dice.'.');
                }

                return $faces[1].' + '.$faces[2];
            }
        };
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
