<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftState;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the offers and the subscriptions.
 */
#[CoversClass(Offer::class)]
#[CoversClass(Subscription::class)]
#[CoversClass(Gift::class)]
#[CoversClass(Seat::class)]
#[CoversClass(OrderAddress::class)]
final class MappingTest extends TestCase
{
    public function testTheOfferTable(): void
    {
        $metadata = $this->metadata(Offer::class);

        self::assertSame('phil_commerce_subscription_offer', $metadata->getTableName());
        self::assertSame(['code'], $metadata->getIdentifierFieldNames());
        self::assertSame([['family']], $this->columnsOf($metadata->table['indexes'] ?? []));
        self::assertSame([
            'priceMinorUnits' => ['price', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'periodUnit' => ['period_unit', 'string', 8, false, null],
            'periodCount' => ['period_count', 'smallint', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'dunning' => ['dunning', 'json', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'family' => ['family', 'string', 64, false, null],
            'rank' => ['tier_rank', 'smallint', null, false, null],
            'name' => ['name', 'string', 255, false, null],
            'trialDays' => ['trial_days', 'smallint', null, false, null],
            'taxCategory' => ['tax_category', 'string', 64, false, null],
            'features' => ['features', 'json', null, false, null],
            'seats' => ['seats', 'integer', null, true, null],
        ], $this->columns($metadata));
        self::assertSame(BillingUnit::class, $metadata->fieldMappings['periodUnit']->enumType);
    }

    public function testTheSubscriptionTable(): void
    {
        $metadata = $this->metadata(Subscription::class);

        self::assertSame('phil_commerce_subscription', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('version', $metadata->versionField);
        self::assertSame([['subscriber'], ['state', 'period_end']], $this->columnsOf($metadata->table['indexes'] ?? []));
        self::assertSame([
            'state' => ['state', 'string', 16, false, null],
            'nextOfferCode' => ['next_offer', 'string', 64, true, null],
            'anchor' => ['anchor', 'datetime_immutable', null, true, null],
            'periodsPaid' => ['periods_paid', 'integer', null, false, null],
            'periodsSinceAnchor' => ['periods_since_anchor', 'integer', null, false, null],
            'periodUnit' => ['period_unit', 'string', 8, true, null],
            'periodCount' => ['period_count', 'smallint', null, false, null],
            'periodEnd' => ['period_end', 'datetime_immutable', null, true, null],
            'cancelAtPeriodEnd' => ['cancel_at_period_end', 'boolean', null, false, null],
            'awaitedOrder' => ['awaited_order', 'string', 64, true, null],
            'failedPayments' => ['failed_payments', 'smallint', null, false, null],
            'pastDueSince' => ['past_due_since', 'datetime_immutable', null, true, null],
            'endedAt' => ['ended_at', 'datetime_immutable', null, true, null],
            'payer' => ['payer', 'string', 255, true, null],
            'periodAmount' => ['period_amount', 'bigint', null, false, null],
            'carriedCredit' => ['carried_credit', 'bigint', null, false, null],
            'awaitedAmount' => ['awaited_amount', 'bigint', null, false, null],
            'awaitedCredit' => ['awaited_credit', 'bigint', null, false, null],
            'changeOfferCode' => ['change_offer', 'string', 64, true, null],
            'changeAt' => ['change_at', 'datetime_immutable', null, true, null],
            'changeSecondsLeft' => ['change_seconds_left', 'integer', null, false, null],
            'changePeriodSeconds' => ['change_period_seconds', 'integer', null, true, null],
            'changeCredit' => ['change_credit', 'bigint', null, false, null],
            'deliveryAddress' => ['delivery_address', 'json', null, true, null],
            'lastShippedAt' => ['last_shipped_at', 'datetime_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'subscriber' => ['subscriber', 'string', 255, false, null],
            'family' => ['family', 'string', 64, false, null],
            'offerCode' => ['offer', 'string', 64, false, null],
            'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
        ], $this->columns($metadata));
        self::assertSame(SubscriptionState::class, $metadata->fieldMappings['state']->enumType);
        self::assertSame(BillingUnit::class, $metadata->fieldMappings['periodUnit']->enumType);
    }

    public function testTheGiftTable(): void
    {
        $metadata = $this->metadata(Gift::class);

        self::assertSame('phil_commerce_subscription_gift', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame('version', $metadata->versionField);
        self::assertSame([['buyer']], $this->columnsOf($metadata->table['indexes'] ?? []));
        self::assertSame([['code_hash']], $this->columnsOf($metadata->table['uniqueConstraints'] ?? []));
        self::assertSame([
            'state' => ['state', 'string', 16, false, null],
            'codeHash' => ['code_hash', 'string', 64, true, true],
            'awaitedOrder' => ['awaited_order', 'string', 64, true, null],
            'periodAmount' => ['period_amount', 'bigint', null, false, null],
            'redeemableUntil' => ['redeemable_until', 'datetime_immutable', null, true, null],
            'beneficiary' => ['beneficiary', 'string', 255, true, null],
            'subscriptionId' => ['subscription', 'string', 36, true, true],
            'closedAt' => ['closed_at', 'datetime_immutable', null, true, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'buyer' => ['buyer', 'string', 255, false, null],
            'offerCode' => ['offer', 'string', 64, false, null],
            'periods' => ['periods', 'smallint', null, false, null],
            'renewal' => ['renewal', 'string', 16, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'createdAt' => ['created_at', 'datetime_immutable', null, false, null],
        ], $this->columns($metadata));
        self::assertSame(GiftState::class, $metadata->fieldMappings['state']->enumType);
        self::assertSame(GiftRenewal::class, $metadata->fieldMappings['renewal']->enumType);
    }

    public function testTheSeatTable(): void
    {
        $metadata = $this->metadata(Seat::class);

        self::assertSame('phil_commerce_subscription_seat', $metadata->getTableName());
        self::assertSame(['subscriptionId', 'member'], $metadata->getIdentifierFieldNames());
        self::assertSame([['member']], $this->columnsOf($metadata->table['indexes'] ?? []));
        self::assertSame([
            'subscriptionId' => ['subscription', 'string', 36, false, true],
            'member' => ['member', 'string', 255, false, null],
            'assignedAt' => ['assigned_at', 'datetime_immutable', null, false, null],
        ], $this->columns($metadata));
    }

    public function testTheOrderAddressTable(): void
    {
        $metadata = $this->metadata(OrderAddress::class);

        self::assertSame('phil_commerce_subscription_order_address', $metadata->getTableName());
        self::assertSame(['orderNumber'], $metadata->getIdentifierFieldNames());
        self::assertSame([['subscriber'], ['kept_since']], $this->columnsOf($metadata->table['indexes'] ?? []));
        self::assertSame([
            'shippedAt' => ['shipped_at', 'datetime_immutable', null, true, null],
            'keptSince' => ['kept_since', 'datetime_immutable', null, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'subscriptionId' => ['subscription_id', 'string', 36, false, true],
            'subscriber' => ['subscriber', 'string', 255, false, null],
            'address' => ['address', 'json', null, false, null],
            'frozenAt' => ['frozen_at', 'datetime_immutable', null, false, null],
        ], $this->columns($metadata));
    }

    /**
     * The longest codes, family and subscriber fit their columns.
     */
    public function testWhatTheyHoldFitsTheirColumns(): void
    {
        $offer = Offer::create(str_repeat('o', 64), str_repeat('f', 64), 0, 'X', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'g');
        $subscription = Subscription::start($offer, str_repeat('s', 255), new DateTimeImmutable());
        $offers = $this->metadata(Offer::class)->fieldMappings;
        $subscriptions = $this->metadata(Subscription::class)->fieldMappings;

        self::assertSame($offers['code']->length, \strlen($offer->code));
        self::assertSame($offers['family']->length, \strlen($offer->family));
        self::assertSame($subscriptions['id']->length, \strlen($subscription->id));
        self::assertSame($subscriptions['subscriber']->length, \strlen($subscription->subscriber));
        self::assertSame($subscriptions['family']->length, \strlen($subscription->family));

        $paid = Offer::create(str_repeat('p', 64), 'f', 0, 'X', Money::of(1, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'g');
        $gift = Gift::buy($paid, StableIdentifier::of(str_repeat('b', 255), 'buyer'), 1, GiftRenewal::EndsAtTerm, new DateTimeImmutable());
        $gifts = $this->metadata(Gift::class)->fieldMappings;
        self::assertSame($gifts['id']->length, \strlen($gift->id));
        self::assertSame($gifts['buyer']->length, \strlen($gift->buyer));
        self::assertSame($gifts['offerCode']->length, \strlen($gift->offerCode));
        self::assertSame($gifts['currencyCode']->length, \strlen($gift->currency()->code));
    }

    /**
     * The columns of each index, in order.
     *
     * @param array<array-key, mixed> $indexes
     *
     * @return list<mixed>
     */
    private function columnsOf(array $indexes): array
    {
        return array_values(array_map(static fn (mixed $index): mixed => \is_array($index) ? ($index['columns'] ?? null) : null, $indexes));
    }

    /**
     * @template T of object
     *
     * @param ClassMetadata<T> $metadata
     *
     * @return array<string, array{string, string, ?int, ?bool, mixed}>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return $columns;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
