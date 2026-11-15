<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Application\Channels;
use Phil\CommerceBundle\Application\OptionDraws;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineChannelRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineOrderRepository;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\AdjustmentsType;
use Phil\CommerceBundle\Infrastructure\Doctrine\Type\OrderLinesType;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * An order built, placed and stored through the container, then read back from a fresh unit of work: the row holds
 * the whole order, lines and adjustments included.
 */
#[CoversClass(PhilCommerceBundle::class)]
#[CoversClass(DoctrineOrderRepository::class)]
#[CoversClass(DoctrineChannelRepository::class)]
#[CoversClass(OrderLinesType::class)]
#[CoversClass(AdjustmentsType::class)]
final class OrderStorageTest extends FunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->createSchema($this->entityManager);
    }

    /**
     * Configured lines keep their options through the row, charged before the taxes, and their draws once paid.
     */
    public function testConfiguredLinesAreReadBackWithTheirOptionsAndDraws(): void
    {
        $repository = $this->service(OrderRepository::class, OrderRepository::class);
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('2026-0002', Currency::of('CAD'));
        $context = PricingContext::of(1, new DateTimeImmutable('2026-11-07T12:00:00Z'));

        $bob = $modifier->add($order, SellableReference::of('product', 'dice'), 1, $context, LineConfiguration::of(['engraving' => OptionValue::chosen('Bob')]));
        $alice = $modifier->add($order, SellableReference::of('product', 'dice'), 1, $context, LineConfiguration::of(['engraving' => OptionValue::chosen('Alice')]));
        $plain = $modifier->add($order, SellableReference::of('product', 'dice'), 1, $context);
        $box = $modifier->add($order, SellableReference::of('product', 'loot'), 2, $context);
        $modifier->changeTaxZone($order, 'QC');
        $modifier->place($order, new DateTimeImmutable('2026-11-07T12:05:00+00:00'));

        self::assertSame(['Engraving', 'Engraving'], array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $order->adjustments(AdjustmentType::Option)));
        self::assertGreaterThan($order->lineAdjustments($plain->reference, AdjustmentType::Tax)[0]->amount->minorUnits, $order->lineAdjustments($bob->reference, AdjustmentType::Tax)[0]->amount->minorUnits, 'The engraving is taxed with its line.');

        $payment = Payment::create($order, 'card', $order->total(), new DateTimeImmutable('2026-11-07T12:06:00+00:00'));
        $payment->apply(PaymentNotification::of('evt_1', 'pi', PaymentEvent::Captured));
        self::assertSame(1, $this->service(OptionDraws::class, OptionDraws::class)->draw($order, OrderPayments::of($order, [$payment])));

        $repository->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $repository->find('2026-0002');

        self::assertNotNull($stored);
        self::assertSame(
            [$bob->reference->value(), $alice->reference->value(), 'product:dice', $box->reference->value()],
            array_map(static fn (OrderLine $line): string => $line->reference->value(), $stored->lines()),
        );
        self::assertTrue($plain->configuration->isEmpty());
        self::assertSame('Bob', $stored->line($bob->reference)?->configuration->value('engraving')?->shown());
        self::assertSame('Alice', $stored->line($alice->reference)?->configuration->value('engraving')?->shown());
        self::assertSame(['item 1', 'item 2'], $stored->line($box->reference)?->configuration->value('content')?->draws);
        self::assertSame(
            array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value()], $order->adjustments()),
            array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value()], $stored->adjustments()),
        );
    }

    public function testAPlacedOrderIsReadBackWhole(): void
    {
        $repository = $this->service(OrderRepository::class, OrderRepository::class);
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $context = PricingContext::of(1, new DateTimeImmutable('2026-11-03T12:00:00Z'));

        $modifier->add($order, SellableReference::of('product', 'dice'), 2, $context);
        $modifier->add($order, SellableReference::of('product', 'pdf'), 1, $context);
        $modifier->changeTaxZone($order, 'QC');
        $modifier->place($order, new DateTimeImmutable('2026-11-03T12:05:00+00:00'));

        $repository->add($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $stored = $repository->find('2026-0001');

        self::assertNotNull($stored);
        self::assertNotSame($order, $stored);
        self::assertSame('CAD', $stored->currency->code);
        self::assertSame(OrderState::Placed, $stored->state());
        self::assertSame('2026-11-03T12:05:00+00:00', $stored->placedAt()?->format(\DATE_ATOM));
        self::assertNull($stored->cancelledAt());
        self::assertSame('QC', $stored->taxZone());
        self::assertSame(['product:dice', 'product:pdf'], array_map(static fn (OrderLine $line): string => $line->reference->value(), $stored->lines()));
        self::assertSame([2, 1], array_map(static fn (OrderLine $line): int => $line->quantity, $stored->lines()));
        self::assertSame(
            array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value()], $order->adjustments(AdjustmentType::Tax)),
            array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value()], $stored->adjustments(AdjustmentType::Tax)),
        );
        self::assertTrue($stored->total()->equals($order->total()));
        self::assertNull($repository->find('2026-0002'));
    }

    /**
     * A channel is stored with its hostnames, found by the host of a request, and opens its orders.
     */
    public function testAChannelIsStoredAndFoundByItsHost(): void
    {
        $channel = Channel::create('second-shop', 'La deuxième boutique', Currency::of('CAD'), 'fr_CA', 'second-shop', 'QC');
        $channel->answerOn('second-shop.example.ca');
        $this->service(ChannelRepository::class, ChannelRepository::class)->add($channel);
        $this->service(ChannelRepository::class, ChannelRepository::class)->add(Channel::create('first-shop', 'La première boutique', Currency::of('CAD'), 'fr_CA'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $channels = $this->service(Channels::class, Channels::class);
        $found = $channels->forHost('second-shop.example.ca');

        self::assertSame(['second-shop', 'fr_CA', 'second-shop', ['second-shop.example.ca']], [$found->code, $found->locale(), $found->theme(), $found->hostnames()]);
        self::assertSame(['first-shop', 'second-shop'], array_map(static fn (Channel $each): string => $each->code, $this->service(ChannelRepository::class, ChannelRepository::class)->all()), 'The channels come in the order of their codes.');
        self::assertSame('QC', $channels->newOrder($found, 'N-1')->taxZone());
    }

    /**
     * A change to a stored order is written with the next commit, and its version moves on.
     */
    public function testAChangeToAStoredOrderIsWrittenAndVersioned(): void
    {
        $repository = $this->service(OrderRepository::class, OrderRepository::class);
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('2026-0003', Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $repository->add($order);
        $this->entityManager->flush();

        $order->cancel(new DateTimeImmutable('2026-11-04T08:00:00+00:00'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $row = $this->service('doctrine.dbal.default_connection', Connection::class)
            ->fetchAssociative('SELECT state, version, currency FROM phil_commerce_order WHERE number = ?', ['2026-0003']);

        self::assertSame(['state' => 'cancelled', 'version' => 2, 'currency' => 'CAD'], $row);
        $stored = $repository->find('2026-0003');
        self::assertNotNull($stored);
        self::assertSame(2, $stored->version());
        self::assertSame(OrderState::Cancelled, $stored->state());
    }
}
