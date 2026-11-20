<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\DocumentRegister;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnSlipRenderer;
use Phil\CommerceReturnsBundle\Application\ReturnDocumentTypes;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Application\ReturnSlips;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Documents;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnSlips::class)]
#[CoversClass(ReturnDocumentTypes::class)]
#[CoversClass(ReturnSlip::class)]
final class ReturnSlipsTest extends TestCase
{
    private InMemoryOrders $orders;

    private InMemoryReturns $returns;

    private Documents $documents;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrders();
        $this->returns = new InMemoryReturns();
        $this->documents = new Documents();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testAnAuthorizedReturnsSlipIsNumberedOnceAndPrinted(): void
    {
        $renderer = new class implements ReturnSlipRenderer {
            /** @var list<array{ReturnSlip, ?string}> */
            public array $rendered = [];

            #[Override]
            public function render(ReturnSlip $slip, ?string $locale = null): string
            {
                $this->rendered[] = [$slip, $locale];

                return '%PDF-slip';
            }
        };
        $slips = $this->slips($renderer);
        $return = $this->authorized();
        $return->attachLabel(ReturnLabel::of('carrier', 'TRACK-1'));

        self::assertSame('%PDF-slip', $slips->print($return->id, $this->at, 'fr'));
        [$slip, $locale] = $renderer->rendered[0];
        self::assertSame(['RS-00000001', $return->id, 'A-1', $this->at, 'fr', 'TRACK-1'], [$slip->number, $slip->returnId, $slip->orderNumber, $slip->printedAt, $locale, $slip->label?->tracking]);
        self::assertSame([['line' => 'product:TEA', 'description' => 'Tea', 'quantity' => 2, 'reason' => ReturnReason::Defective], ['line' => 'product:MUG', 'description' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $slip->lines);
        self::assertSame('RS-00000001', $return->slipNumber());

        self::assertSame('RS-00000001', $slips->slipFor($return->id, $this->at)->number, 'A later print keeps the number.');
        self::assertSame(['return_slip' => 1], $this->documents->sequences);
    }

    public function testOnlyAnAuthorizedReturnHasASlip(): void
    {
        $return = $this->authorized();
        new Returns($this->orders, $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()))->cancel($return->id, $this->at);

        try {
            $this->slips()->slipFor($return->id, $this->at);
            self::fail('A cancelled return got a slip.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s is cancelled: only an authorized return gets a label or a slip.', $return->id), $returnRefused->getMessage());
            self::assertSame([], $this->documents->sequences);
        }

        $this->expectException(ReturnRefused::class);

        $this->slips()->slipFor('nope', $this->at);
    }

    public function testASlipIsNumberedOnce(): void
    {
        $return = $this->authorized();
        $return->numberSlip('RS-1');

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The return %s already has the slip RS-1.', $return->id));

        $return->numberSlip('RS-2');
    }

    public function testTheSlipsSeriesIsDeclared(): void
    {
        $types = iterator_to_array(new ReturnDocumentTypes('RET-', 6)->documentTypes(), false);

        self::assertSame([['return_slip', 'RET-', 6]], array_map(static fn (DocumentType $type): array => [$type->code, $type->prefix, $type->digits], $types));
        self::assertSame(['RS-', 8], array_map(static fn (DocumentType $type): array => [$type->prefix, $type->digits], iterator_to_array(new ReturnDocumentTypes()->documentTypes(), false))[0]);
    }

    private function slips(?ReturnSlipRenderer $renderer = null): ReturnSlips
    {
        $renderer ??= new class implements ReturnSlipRenderer {
            #[Override]
            public function render(ReturnSlip $slip, ?string $locale = null): string
            {
                return '';
            }
        };

        return new ReturnSlips($this->returns, $this->orders, new DocumentRegister([new ReturnDocumentTypes()], $this->documents), $renderer);
    }

    private function authorized(): ReturnRequest
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, $cad), 'standard'), 3, Money::of(500, $cad));
        $order->add(Sellable::of(SellableReference::of('product', 'MUG'), 'Mug', Money::of(900, $cad), 'standard'), 1, Money::of(900, $cad));
        $order->place($this->at);

        $this->orders->add($order);
        $returns = new Returns($this->orders, $this->returns, new Payments([new ReturnsGateway()], new InMemoryPayments()));
        $return = $returns->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Defective], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $returns->authorize($return->id, $this->at);
        // The order as the store reads it later, its mug's description lost: the slip names the line.
        $later = Order::open('A-1', $cad);
        $later->add(Sellable::of(SellableReference::of('product', 'TEA'), 'Tea', Money::of(500, $cad), 'standard'), 3, Money::of(500, $cad));

        $this->orders->add($later);

        return $return;
    }
}
