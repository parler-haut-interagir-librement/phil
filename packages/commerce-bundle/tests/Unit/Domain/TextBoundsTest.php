<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\CommerceBundle\Application\SecretToken;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Model\WishList;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\OrderChangeLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The exact reach of the patterns and limits that guard the text the order's life stores: a final line break, a
 * character spread over several bytes, the longest value and the one past it.
 */
#[CoversClass(Refund::class)]
#[CoversClass(OrderChange::class)]
#[CoversClass(OrderStateChange::class)]
#[CoversClass(WishList::class)]
#[CoversClass(ManualDiscount::class)]
#[CoversClass(GiftOptions::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(DocumentType::class)]
#[CoversClass(SecretToken::class)]
#[CoversClass(PaymentLink::class)]
#[CoversClass(Order::class)]
final class TextBoundsTest extends TestCase
{
    private const string HASH = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * @return iterable<string, array{Closure(string): mixed}>
     */
    public static function oneLineTexts(): iterable
    {
        yield 'a refund reason' => [static fn (string $text): mixed => Refund::grant(self::payment(), self::cad(1), $text, 'clerk-1', new DateTimeImmutable())];
        yield 'a change reason' => [static fn (string $text): mixed => OrderChange::prepare(self::order(), [OrderChangeLine::of(SellableReference::of('product', 'dice'), 1)], $text, 'clerk-1', new DateTimeImmutable(), self::cad(0))];
        yield 'a forced move reason' => [static fn (string $text): mixed => OrderStateChange::forced('2026-0001', OrderState::Placed, OrderState::Cancelled, 'clerk-1', $text, new DateTimeImmutable())];
        yield 'a wish list name' => [static fn (string $text): mixed => WishList::create('member-1', $text, new DateTimeImmutable())];
        yield 'a manual discount reason' => [static fn (string $text): mixed => ManualDiscount::amount('promo', self::cad(1), $text, 'clerk-1')];
    }

    /**
     * @param Closure(string): mixed $make
     */
    #[DataProvider('oneLineTexts')]
    public function testTheLongestLineOfCharactersIsAccepted(Closure $make): void
    {
        self::assertNotNull($make(str_repeat('é', 255)));
        self::assertNotNull($make('日本語 … fine'));
    }

    /**
     * @param Closure(string): mixed $make
     */
    #[DataProvider('oneLineTexts')]
    public function testAnythingButOneLineOfAtMost255CharactersIsRefused(Closure $make): void
    {
        $refusals = 0;

        foreach ([str_repeat('é', 256), '   ', "a\tb", "text\n", ''] as $refused) {
            try {
                $make($refused);
                self::fail(\sprintf('"%s" was accepted.', $refused));
            } catch (InvalidValue) {
                ++$refusals;
            }
        }

        self::assertSame(5, $refusals);
    }

    public function testAGiftMessageKeepsItsCharactersWhateverTheirBytes(): void
    {
        self::assertSame('Joyeux Noël … 日本', GiftOptions::of(true, false, 'Joyeux Noël … 日本')->message);
    }

    public function testNothingIsWrappedOrHiddenByDefault(): void
    {
        $options = GiftOptions::of();

        self::assertFalse($options->wrapped);
        self::assertFalse($options->recycledWrap);
        self::assertNull($options->message);
        self::assertFalse($options->pricesHidden);
    }

    /**
     * @return iterable<string, array{Closure(): mixed}>
     */
    public static function plainValues(): iterable
    {
        yield 'an identifier' => [static fn (): mixed => Identifier::check("clerk-1\n", 'actor')];
        yield 'a discount key' => [static fn (): mixed => ManualDiscount::amount("promo\n", self::cad(1), 'Reason', 'clerk-1')];
        yield 'a document type code' => [static fn (): mixed => DocumentType::of("slip\n", 'S-')];
        yield 'a document type prefix' => [static fn (): mixed => DocumentType::of('slip', "S-\n")];
        yield 'a payment link hash' => [static fn (): mixed => PaymentLink::create('2026-0001', self::HASH."\n", new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'))];
        yield 'a payment link hash after text' => [static fn (): mixed => PaymentLink::create('2026-0001', 'z'.self::HASH, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'))];
        yield 'a payment link hash before text' => [static fn (): mixed => PaymentLink::create('2026-0001', self::HASH.'z', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'))];
        yield 'a wish list hash' => [static function (): void {
            WishList::create('member-1', 'List', new DateTimeImmutable())->shareUnder(self::HASH."\n");
        }];
        yield 'a wish list hash after text' => [static function (): void {
            WishList::create('member-1', 'List', new DateTimeImmutable())->shareUnder('z'.self::HASH);
        }];
        yield 'a wish list hash before text' => [static function (): void {
            WishList::create('member-1', 'List', new DateTimeImmutable())->shareUnder(self::HASH.'z');
        }];
        yield 'a wish list id' => [static function (): void {
            self::order(false)->buyFromWishList("0190e0b4-7a52-7d3e-8a45-3f2b1c0d9e8f\n");
        }];
        yield 'a wish list id after text' => [static function (): void {
            self::order(false)->buyFromWishList('z0190e0b4-7a52-7d3e-8a45-3f2b1c0d9e8f');
        }];
        yield 'a wish list id before text' => [static function (): void {
            self::order(false)->buyFromWishList('0190e0b4-7a52-7d3e-8a45-3f2b1c0d9e8fz');
        }];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('plainValues')]
    public function testAPatternMatchesTheWholeValueAndNotItsFinalLineBreak(Closure $make): void
    {
        $this->expectException(InvalidValue::class);

        $make();
    }

    public function testAVisitorsTokenIsHashedOnlyWhenItIsExactlyTheShapeOfOne(): void
    {
        $token = str_repeat('a', 32);

        self::assertSame(hash('sha256', $token), SecretToken::hash($token));
        self::assertNull(SecretToken::hash($token."\n"));
        self::assertNull(SecretToken::hash('x'.$token));
        self::assertNull(SecretToken::hash($token.'x'));
    }

    public function testADocumentTypeAcceptsItsLongestCodeAndPrefix(): void
    {
        self::assertSame('a'.str_repeat('1', 15), DocumentType::of('a'.str_repeat('1', 15), str_repeat('P', 16), 8)->code);
    }

    private static function order(bool $placed = true): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', self::cad(1_000), 'goods'), 1, self::cad(1_000));

        if ($placed) {
            $order->place(new DateTimeImmutable());
        }

        return $order;
    }

    private static function payment(): Payment
    {
        return Payment::create(self::order(), 'card', self::cad(1_000), new DateTimeImmutable());
    }

    private static function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }
}
