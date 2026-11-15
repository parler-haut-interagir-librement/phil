<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Exception\QuoteRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Quote;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Order\QuoteState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Quote::class)]
#[CoversClass(Order::class)]
#[CoversClass(QuoteRefused::class)]
final class QuoteTest extends TestCase
{
    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testACartIsQuotedThenPlacedOrReopened(): void
    {
        $order = $this->cart();

        $order->quote();
        self::assertSame(OrderState::Quoted, $order->state());

        try {
            $order->add(Sellable::of(SellableReference::of('product', 'B'), 'B', Money::of(100, Currency::of('CAD')), 'standard'), 1, Money::of(100, Currency::of('CAD')));
            self::fail('A quoted order changed.');
        } catch (OrderLocked) {
        }

        $order->reopen();
        self::assertSame(OrderState::Cart, $order->state());

        $order->quote();
        $order->place($this->at);
        self::assertSame([OrderState::Placed, $this->at], [$order->state(), $order->placedAt()]);
    }

    public function testAPlacedOrderIsReopenedToBeAmended(): void
    {
        $order = $this->cart();
        $order->place($this->at);

        $order->reopen();

        self::assertSame([OrderState::Cart, null], [$order->state(), $order->placedAt()]);
    }

    public function testAQuoteIsCancelled(): void
    {
        $order = $this->cart();
        $order->quote();

        $order->cancel($this->at);

        self::assertSame(OrderState::Cancelled, $order->state());
    }

    public function testAnEmptyCartIsNotQuoted(): void
    {
        $order = Order::open('7', Currency::of('CAD'));

        try {
            $order->quote();
            self::fail('An empty cart was quoted.');
        } catch (OrderLocked $orderLocked) {
            self::assertSame('The order 7 has no line: an empty cart cannot be placed.', $orderLocked->getMessage());
        }

        self::assertSame(OrderState::Cart, $order->state());
    }

    public function testOnlyACartIsQuotedAndOnlyAQuoteOrAPlacedOrderReopened(): void
    {
        $quoted = $this->cart();
        $quoted->quote();

        $cancelled = $this->cart();
        $cancelled->cancel($this->at);

        foreach ([[$quoted, 'quote', 'quoted', 'quoted'], [$this->cart(), 'reopen', 'cart', 'cart'], [$cancelled, 'reopen', 'cancelled', 'cart']] as [$order, $move, $from, $to]) {
            try {
                'quote' === $move ? $order->quote() : $order->reopen();
                self::fail('A move was allowed.');
            } catch (OrderLocked $locked) {
                self::assertStringContainsString(\sprintf('is %s: it cannot become %s.', $from, $to), $locked->getMessage());
            }
        }

        $placed = $this->cart();
        $placed->place($this->at);
        $this->expectException(OrderLocked::class);
        $placed->quote();
    }

    public function testAQuoteIsAcceptedOrDeclinedOnce(): void
    {
        $validUntil = $this->at->modify('+30 days');
        $quote = Quote::of($this->cart(), str_repeat('é', 255), $this->at, $validUntil);

        self::assertSame(['1', str_repeat('é', 255), $this->at, $validUntil, QuoteState::Pending, null], [$quote->orderNumber, $quote->customer, $quote->issuedAt, $quote->validUntil, $quote->state(), $quote->answeredAt()]);
        self::assertFalse($quote->hasExpired($validUntil));
        self::assertTrue($quote->hasExpired($validUntil->modify('+1 second')));

        $quote->accept($validUntil);
        self::assertSame([QuoteState::Accepted, $validUntil], [$quote->state(), $quote->answeredAt()]);

        foreach ([static fn () => $quote->accept($validUntil), static fn () => $quote->decline($validUntil)] as $answer) {
            try {
                $answer();
                self::fail('A quote was answered twice.');
            } catch (QuoteRefused $refused) {
                self::assertSame('The quote of 1 was already answered.', $refused->getMessage());
            }
        }

        $declined = Quote::of($this->cart(), 'ada', $this->at, $validUntil);
        $declined->decline($this->at);
        self::assertSame([QuoteState::Declined, $this->at], [$declined->state(), $declined->answeredAt()]);
    }

    public function testAnExpiredQuoteIsNotAccepted(): void
    {
        $quote = Quote::of($this->cart(), 'ada', $this->at, $this->at->modify('+1 day'));

        try {
            $quote->accept($this->at->modify('+2 days'));
            self::fail('An expired quote was accepted.');
        } catch (QuoteRefused $quoteRefused) {
            self::assertSame('The quote of 1 has expired.', $quoteRefused->getMessage());
        }

        self::assertSame(QuoteState::Pending, $quote->state());
        $quote->decline($this->at->modify('+2 days'));
        self::assertSame(QuoteState::Declined, $quote->state());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badQuotes(): iterable
    {
        yield 'no customer' => [' ', '+1 day', 'A quote is offered to a customer, named in 1 to 255 characters.'];
        yield 'a long name' => [str_repeat('é', 256), '+1 day', 'A quote is offered to a customer, named in 1 to 255 characters.'];
        yield 'valid at once' => ['ada', '+0 seconds', 'A quote is valid after it is issued.'];
    }

    #[DataProvider('badQuotes')]
    public function testAQuoteHasACustomerAndADate(string $customer, string $until, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Quote::of($this->cart(), $customer, $this->at, $this->at->modify($until));
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'No quote is known for the order 1.',
            'The order 1 has its receipt: it no longer changes; credit it instead.',
        ], [QuoteRefused::unknown('1')->getMessage(), QuoteRefused::receiptIssued('1')->getMessage()]);
        self::assertInstanceOf(DomainError::class, QuoteRefused::unknown('1'));
    }

    public function testTheTable(): void
    {
        $metadata = new ClassMetadata(Quote::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass(Quote::class, $metadata);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable];
        }

        self::assertSame(['phil_commerce_quote', ['orderNumber']], [$metadata->getTableName(), $metadata->getIdentifierFieldNames()]);
        self::assertSame([
            'state' => ['state', 'string', 16, false],
            'answeredAt' => ['answered_at', 'datetime_immutable', null, true],
            'orderNumber' => ['order_number', 'string', 64, false],
            'customer' => ['customer', 'string', 255, false],
            'issuedAt' => ['issued_at', 'datetime_immutable', null, false],
            'validUntil' => ['valid_until', 'datetime_immutable', null, false],
        ], $columns);

        $quote = Quote::of($this->cart(str_repeat('o', 64)), str_repeat('c', 255), $this->at, $this->at->modify('+1 day'));
        self::assertSame($metadata->fieldMappings['orderNumber']->length, \strlen($quote->orderNumber));
        self::assertSame($metadata->fieldMappings['customer']->length, \strlen($quote->customer));
    }

    private function cart(string $number = '1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', Money::of(1_000, Currency::of('CAD')), 'standard'), 1, Money::of(1_000, Currency::of('CAD')));

        return $order;
    }
}
