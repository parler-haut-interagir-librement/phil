<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\Subscription;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Subscription\Price;
use Phil\InventoryAlerts\Subscription\Subscription;
use Phil\InventoryAlerts\Subscription\SubscriptionKind;
use Phil\InventoryAlerts\Subscription\Subscriptions;
use Phil\InventoryAlerts\Subscription\UnsubscribeTokens;
use Phil\InventoryAlerts\Tests\Support\MemorySubscriptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscriptions::class)]
#[CoversClass(Subscription::class)]
#[CoversClass(SubscriptionKind::class)]
#[CoversClass(UnsubscribeTokens::class)]
#[CoversClass(Price::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class SubscriptionsTest extends TestCase
{
    private const string SECRET = 'a secret of thirty-two bytes ok!';

    private MemorySubscriptions $store;

    private Subscriptions $subscriptions;

    protected function setUp(): void
    {
        $this->store = new MemorySubscriptions();
        $this->subscriptions = new Subscriptions($this->store, new UnsubscribeTokens(self::SECRET));
    }

    public function testACustomerSubscribesOnceToABackInStockAlert(): void
    {
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $email = EmailAddress::parse('ana@example.org');

        $subscription = $this->subscriptions->subscribeBackInStock('MUG', $email, $at);
        $again = $this->subscriptions->subscribeBackInStock('MUG', EmailAddress::parse('ana@example.org'), new DateTimeImmutable('2026-11-22'));

        self::assertSame($subscription, $again);
        self::assertSame([$subscription->id => $subscription], $this->store->rows);
        self::assertSame(SubscriptionKind::BackInStock, $subscription->kind);
        self::assertSame('MUG', $subscription->sku);
        self::assertSame($email, $subscription->email);
        self::assertSame($at, $subscription->subscribedAt);
        self::assertNull($subscription->threshold);
        self::assertTrue($subscription->isActive());
    }

    public function testANotifiedSubscriptionMakesRoomForANewOne(): void
    {
        $email = EmailAddress::parse('ana@example.org');
        $first = $this->subscriptions->subscribeBackInStock('MUG', $email, new DateTimeImmutable());
        $this->store->markNotified([$first->id], new DateTimeImmutable());

        self::assertNotSame($first->id, $this->subscriptions->subscribeBackInStock('MUG', $email, new DateTimeImmutable())->id);
    }

    public function testAPriceDropSubscriptionKeepsItsPlaceAndTakesTheNewThreshold(): void
    {
        $email = EmailAddress::parse('ana@example.org');
        $at = new DateTimeImmutable('2026-11-21 09:00');
        $first = $this->subscriptions->subscribePriceDrop('MUG', $email, new Price(2_000, 'CAD'), $at);
        $second = $this->subscriptions->subscribePriceDrop('MUG', $email, new Price(1_500, 'CAD'), new DateTimeImmutable('2026-11-25'));

        self::assertSame($first->id, $second->id);
        self::assertSame($at, $second->subscribedAt);
        self::assertSame(1_500, $second->threshold?->amount);
        self::assertSame(SubscriptionKind::PriceDrop, $second->kind);
        self::assertSame([$first->id => $second], $this->store->rows);
        self::assertSame(2_000, $first->threshold?->amount);
    }

    public function testBackInStockAndPriceDropAreSeparateSubscriptions(): void
    {
        $email = EmailAddress::parse('ana@example.org');
        $this->subscriptions->subscribeBackInStock('MUG', $email, new DateTimeImmutable());
        $this->subscriptions->subscribePriceDrop('MUG', $email, new Price(1_000, 'CAD'), new DateTimeImmutable());

        self::assertCount(2, $this->store->rows);
    }

    public function testTheUnsubscriptionLinkDeletesTheSubscriptionInOneClick(): void
    {
        $subscription = $this->subscriptions->subscribeBackInStock('MUG', EmailAddress::parse('ana@example.org'), new DateTimeImmutable());
        $token = $this->subscriptions->unsubscribeToken($subscription);

        self::assertTrue($this->subscriptions->unsubscribe($token));
        self::assertSame([], $this->store->rows);
        // A second click finds nothing to delete and says the link is good.
        self::assertTrue($this->subscriptions->unsubscribe($token));
    }

    public function testATokenOfAnotherSecretDeletesNothing(): void
    {
        $subscription = $this->subscriptions->subscribeBackInStock('MUG', EmailAddress::parse('ana@example.org'), new DateTimeImmutable());
        $token = new UnsubscribeTokens(str_repeat('x', 32))->issue($subscription->id);

        self::assertFalse($this->subscriptions->unsubscribe($token));
        self::assertCount(1, $this->store->rows);
    }

    public function testNotifiedSubscriptionsArePurged(): void
    {
        $email = EmailAddress::parse('ana@example.org');
        $old = $this->subscriptions->subscribeBackInStock('MUG', $email, new DateTimeImmutable());
        $recent = $this->subscriptions->subscribeBackInStock('CAP', $email, new DateTimeImmutable());
        $active = $this->subscriptions->subscribeBackInStock('BAG', $email, new DateTimeImmutable());
        $this->store->markNotified([$old->id], new DateTimeImmutable('2026-11-01'));
        $this->store->markNotified([$recent->id], new DateTimeImmutable('2026-11-20'));

        self::assertSame(1, $this->subscriptions->purgeNotified(new DateTimeImmutable('2026-11-10')));
        self::assertSame([$recent->id, $active->id], array_keys($this->store->rows));
    }

    public function testATokenIsTheIdentifierAndItsSignature(): void
    {
        $tokens = new UnsubscribeTokens(self::SECRET);
        $id = str_repeat('ab', 16);
        $token = $tokens->issue($id);
        $expected = sodium_bin2base64(hash_hmac('sha256', 'phil/inventory-alerts unsubscribe '.$id, self::SECRET, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame($id.'.'.$expected, $token);
        self::assertSame($id, $tokens->verify($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedTokens(): iterable
    {
        $id = str_repeat('ab', 16);
        $good = new UnsubscribeTokens(self::SECRET)->issue($id);

        yield 'empty' => [''];
        yield 'another identifier' => [str_repeat('cd', 16).substr($good, 32)];
        yield 'a signature changed' => [substr($good, 0, -1).(str_ends_with($good, 'A') ? 'B' : 'A')];
        yield 'a character more' => [$good.'A'];
        yield 'a prefix' => [' '.$good];
        yield 'a line feed after' => [$good."\n"];
        yield 'upper case identifier' => [strtoupper(substr($good, 0, 32)).substr($good, 32)];
        yield 'no dot' => [str_replace('.', '', $good)];
    }

    #[DataProvider('refusedTokens')]
    public function testATokenThisSecretDidNotSignIsRefused(string $token): void
    {
        self::assertNull(new UnsubscribeTokens(self::SECRET)->verify($token));
    }

    public function testTheSecretHasAtLeast32Bytes(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The unsubscription secret must have at least 32 bytes.');

        new UnsubscribeTokens(str_repeat('x', 31));
    }

    public function testANewIdentifierIs32HexadecimalCharacters(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', Subscription::newId());
        self::assertNotSame(Subscription::newId(), Subscription::newId());
    }

    public function testANotifiedSubscriptionIsNoLongerActive(): void
    {
        $subscription = $this->subscription(SubscriptionKind::PriceDrop, new Price(100, 'CAD'));
        $at = new DateTimeImmutable('2026-11-21 10:00');
        $notified = $subscription->notified($at);

        self::assertFalse($notified->isActive());
        self::assertSame($at, $notified->notifiedAt);
        self::assertEquals([$subscription->id, $subscription->kind, $subscription->sku, $subscription->email, $subscription->subscribedAt, $subscription->threshold], [$notified->id, $notified->kind, $notified->sku, $notified->email, $notified->subscribedAt, $notified->threshold]);
    }

    /**
     * @return iterable<string, array{callable(): object, string}>
     */
    public static function refusedSubscriptions(): iterable
    {
        $email = EmailAddress::parse('ana@example.org');
        $at = new DateTimeImmutable();

        yield 'a short identifier' => [static fn (): Subscription => new Subscription(str_repeat('a', 31), SubscriptionKind::BackInStock, 'MUG', $email, $at), 'The subscription identifier must be 32 lowercase hexadecimal characters.'];
        yield 'an identifier with a prefix' => [static fn (): Subscription => new Subscription('x'.str_repeat('a', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at), 'The subscription identifier must be 32 lowercase hexadecimal characters.'];
        yield 'an identifier with a line feed' => [static fn (): Subscription => new Subscription(str_repeat('a', 32)."\n", SubscriptionKind::BackInStock, 'MUG', $email, $at), 'The subscription identifier must be 32 lowercase hexadecimal characters.'];
        yield 'an empty SKU' => [static fn (): Subscription => new Subscription(str_repeat('a', 32), SubscriptionKind::BackInStock, '', $email, $at), 'The SKU must have 1 to 128 bytes and no control character.'];
        yield 'a price drop without threshold' => [static fn (): Subscription => new Subscription(str_repeat('a', 32), SubscriptionKind::PriceDrop, 'MUG', $email, $at), 'The price threshold must be given for a price-drop alert, and only for one.'];
        yield 'a back-in-stock alert with a threshold' => [static fn (): Subscription => new Subscription(str_repeat('a', 32), SubscriptionKind::BackInStock, 'MUG', $email, $at, new Price(1, 'CAD')), 'The price threshold must be given for a price-drop alert, and only for one.'];
        yield 'a negative price' => [static fn (): Price => new Price(-1, 'CAD'), 'The price must be a whole number from 0 to 1,000,000,000,000.'];
        yield 'a currency in lower case' => [static fn (): Price => new Price(1, 'cad'), 'The currency must be three capital letters (ISO 4217).'];
        yield 'a currency of four letters' => [static fn (): Price => new Price(1, 'CADX'), 'The currency must be three capital letters (ISO 4217).'];
        yield 'a currency and a line feed' => [static fn (): Price => new Price(1, "CAD\n"), 'The currency must be three capital letters (ISO 4217).'];
    }

    /**
     * @param callable(): object $make
     */
    #[DataProvider('refusedSubscriptions')]
    public function testASubscriptionOutOfRangeIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAPriceIsAtOrBelowAThresholdOfItsCurrency(): void
    {
        $threshold = new Price(1_000, 'CAD');

        self::assertTrue(new Price(1_000, 'CAD')->isAtOrBelow($threshold));
        self::assertTrue(new Price(0, 'CAD')->isAtOrBelow($threshold));
        self::assertFalse(new Price(1_001, 'CAD')->isAtOrBelow($threshold));
        self::assertFalse(new Price(500, 'USD')->isAtOrBelow($threshold));
    }

    private function subscription(SubscriptionKind $kind, ?Price $threshold = null): Subscription
    {
        return new Subscription(Subscription::newId(), $kind, 'MUG', EmailAddress::parse('ana@example.org'), new DateTimeImmutable('2026-11-21 09:00'), $threshold);
    }
}
