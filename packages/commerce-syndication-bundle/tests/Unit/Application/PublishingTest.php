<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Phil\CommerceSyndicationBundle\Application\Forwarder;
use Phil\CommerceSyndicationBundle\Application\Ledger;
use Phil\CommerceSyndicationBundle\Application\Partners as PartnerService;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\RecordingTransport;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\StandardWebhooks\Tolerance;
use Phil\StandardWebhooks\Verifier;
use Phil\StandardWebhooks\WebhookHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartnerService::class)]
#[CoversClass(Publisher::class)]
#[CoversClass(Courier::class)]
#[CoversClass(Ledger::class)]
final class PublishingTest extends TestCase
{
    private InMemorySyndication $store;

    protected function setUp(): void
    {
        $this->store = new InMemorySyndication();
    }

    public function testPartnersAreRegisteredAndFed(): void
    {
        $partners = new PartnerService($this->store);

        $reseller = $partners->register('reseller', 'The reseller', PartnerRole::Reseller, 'https://reseller.example/in', Partners::resellerKey()->publicKey()->toString());
        $partners->feed('reseller', ['TEA-1'], 250_000);

        self::assertSame($reseller, $partners->get('reseller'));
        self::assertSame([['TEA-1'], 250_000], [$reseller->skus(), $reseller->share()]);

        $this->expectException(UnknownPartner::class);
        $partners->get('nobody');
    }

    public function testEachResellerFedTheSkuIsSentItsCost(): void
    {
        $this->store->addPartner(Partners::reseller('third', ['MUG'], 0));
        $this->store->addPartner(Partners::reseller('first', ['TEA-1'], 300_000));
        $this->store->addPartner(Partners::reseller('second', ['TEA-1'], 333_333));

        $suspended = Partners::reseller('fourth', ['TEA-1']);
        $suspended->suspend();

        $this->store->addPartner($suspended);
        $this->store->addPartner(Partners::supplier());

        $at = new DateTimeImmutable('2026-11-03 10:00:00');

        $queued = new Publisher($this->store, $this->store, $this->store)->publish('TEA-1', 'Green tea', Money::of(1_001, Currency::of('CAD')), 'food', 12, true, $at);

        self::assertSame(2, $queued);
        self::assertSame([
            ['first', 'product.updated', ['sku' => 'TEA-1', 'name' => 'Green tea', 'currency' => 'CAD', 'retail' => 1_001, 'cost' => 701, 'tax_category' => 'food', 'available' => 12, 'active' => true]],
            ['second', 'product.updated', ['sku' => 'TEA-1', 'name' => 'Green tea', 'currency' => 'CAD', 'retail' => 1_001, 'cost' => 667, 'tax_category' => 'food', 'available' => 12, 'active' => true]],
        ], array_map(static fn (OutgoingMessage $message): array => [$message->partner, $message->type, $message->data], $this->store->outbox));
    }

    /**
     * The supplier keeps each price it published to a reseller, once per change, to check forwarded orders against.
     */
    public function testEachPricePublishedIsKeptOncePerChange(): void
    {
        $this->store->addPartner(Partners::reseller('first', ['TEA-1'], 300_000));
        $this->store->addPartner(Partners::reseller('second', ['TEA-1'], 500_000));

        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $publisher = new Publisher($this->store, $this->store, $this->store);

        $publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 12, true, $at);
        $publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 11, true, $at->modify('+1 hour'));
        $publisher->publish('TEA-1', 'Green tea', Money::of(1_200, Currency::of('CAD')), 'food', 11, true, $at->modify('+2 hours'));
        $publisher->publish('TEA-1', 'Green tea', Money::of(1_200, Currency::of('USD')), 'food', 11, true, $at->modify('+3 hours'));

        self::assertSame([
            ['first', 'TEA-1', 'CAD', 1_000, 700, '10:00'],
            ['second', 'TEA-1', 'CAD', 1_000, 500, '10:00'],
            ['first', 'TEA-1', 'CAD', 1_200, 840, '12:00'],
            ['second', 'TEA-1', 'CAD', 1_200, 600, '12:00'],
            ['first', 'TEA-1', 'USD', 1_200, 840, '13:00'],
            ['second', 'TEA-1', 'USD', 1_200, 600, '13:00'],
        ], array_map(static fn (PublishedPrice $price): array => [$price->reseller, $price->sku, $price->retail->currency->code, $price->retail->minorUnits, $price->cost->minorUnits, $price->publishedAt->format('H:i')], $this->store->prices));
        self::assertSame(840, $this->store->latestPrice('first', 'TEA-1', 'CAD')?->cost->minorUnits);
        self::assertNull($this->store->latestPrice('first', 'MUG', 'CAD'));
    }

    public function testTheCourierSignsAndDeliversTheDueMessages(): void
    {
        $this->store->addPartner(Partners::reseller());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        new Publisher($this->store, $this->store, $this->store)->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 1, true, $at);
        $transport = new RecordingTransport([204]);

        $delivered = new Courier($this->store, $this->store, $transport, Partners::ourKey()->toString(), 8)->deliver($at);

        self::assertSame(1, $delivered);
        [$url, $headers, $body] = $transport->sent[0];
        self::assertSame('https://reseller.example/syndication', $url);
        self::assertSame('application/json', $headers['content-type']);
        self::assertSame($this->store->outbox[0]->id, $headers['webhook-id']);
        self::assertSame((string) $at->getTimestamp(), $headers['webhook-timestamp']);
        self::assertSame($this->store->outbox[0]->body(), $body);
        $verified = new Verifier()->verify(WebhookHeaders::fromStrings($headers['webhook-id'], $headers['webhook-timestamp'], $headers['webhook-signature']), $body, [Partners::ourKey()->publicKey()], Tolerance::ofSeconds(300), $at);
        self::assertSame('reseller', $verified->envelope()->audience);
        self::assertSame($at, $this->store->outbox[0]->deliveredAt());
        self::assertSame(0, new Courier($this->store, $this->store, $transport, Partners::ourKey()->toString(), 8)->deliver($at));
    }

    /**
     * A partner that fails is tried again later, and its other messages wait for the next run: one timeout per run,
     * not one per message.
     */
    public function testAFailedDeliveryIsTriedAgainLaterAndThePartnersOtherMessagesWait(): void
    {
        $this->store->addPartner(Partners::reseller());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $publisher = new Publisher($this->store, $this->store, $this->store);

        foreach ([1, 0, 2, 3] as $available) {
            $publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', $available, true, $at);
        }

        $transport = new RecordingTransport([500]);
        self::assertSame(0, new Courier($this->store, $this->store, $transport, Partners::ourKey()->toString(), 8)->deliver($at));

        self::assertCount(1, $transport->sent);
        self::assertSame([['HTTP 500.', 1], [null, 0], [null, 0], [null, 0]], array_map(static fn (OutgoingMessage $message): array => [$message->lastError(), $message->attempts()], $this->store->outbox));
        self::assertSame([null, null, null, null], array_map(static fn (OutgoingMessage $message): ?DateTimeImmutable => $message->claimedUntil(), $this->store->outbox));
        self::assertSame(4, new Courier($this->store, $this->store, new RecordingTransport([200, 299, 204, 201]), Partners::ourKey()->toString(), 8)->deliver($at->modify('+1 minute')));
        self::assertSame([2, null], [$this->store->outbox[0]->attempts(), $this->store->outbox[0]->lastError()]);
    }

    /**
     * No answer, an answer that is not one, and the 4xx that may change later are tried again; any other 4xx gives the
     * message up at once, without a second attempt.
     */
    public function testWhatIsTriedAgainAndWhatIsGivenUp(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $statuses = [0, 199, 300, 404, 408, 425, 429, 503, 400, 401, 403, 410, 422];

        foreach (array_keys($statuses) as $i) {
            $reseller = Partners::reseller('r'.$i);
            $this->store->addPartner($reseller);
            $this->store->queue(OutgoingMessage::to($reseller, Forwarder::TYPE, ['order' => 'A-'.$i, 'ship_to' => ['name' => 'Ada']], $at));
        }

        self::assertSame(0, new Courier($this->store, $this->store, new RecordingTransport($statuses), Partners::ourKey()->toString(), 8)->deliver($at));

        $retried = \array_slice($this->store->outbox, 0, 8);
        $refused = \array_slice($this->store->outbox, 8);
        self::assertSame(['No answer.', 'HTTP 199.', 'HTTP 300.', 'HTTP 404.', 'HTTP 408.', 'HTTP 425.', 'HTTP 429.', 'HTTP 503.'], array_map(static fn (OutgoingMessage $message): ?string => $message->lastError(), $retried));
        self::assertSame(array_fill(0, 8, '2026-11-03 10:01:00'), array_map(static fn (OutgoingMessage $message): ?string => $message->nextAttemptAt()?->format('Y-m-d H:i:s'), $retried));
        self::assertSame(array_fill(0, 8, ['name' => 'Ada']), array_map(static fn (OutgoingMessage $message): mixed => $message->data['ship_to'] ?? null, $retried));
        self::assertSame(['HTTP 400. Refused by the partner: given up.', 'HTTP 401. Refused by the partner: given up.', 'HTTP 403. Refused by the partner: given up.', 'HTTP 410. Refused by the partner: given up.', 'HTTP 422. Refused by the partner: given up.'], array_map(static fn (OutgoingMessage $message): ?string => $message->lastError(), $refused));
        self::assertSame(array_fill(0, 5, [null, $at, 1, false]), array_map(static fn (OutgoingMessage $message): array => [$message->nextAttemptAt(), $message->givenUpAt(), $message->attempts(), \array_key_exists('ship_to', $message->data)], $refused));
    }

    public function testAMessageAnotherRunHoldsIsLeftToIt(): void
    {
        $this->store->addPartner(Partners::reseller());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $first = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);
        $second = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);
        $this->store->queue($first);
        $this->store->queue($second);
        $this->store->heldElsewhere = [$first->id];

        $transport = new RecordingTransport();

        self::assertSame(1, new Courier($this->store, $this->store, $transport, Partners::ourKey()->toString(), 8)->deliver($at));

        self::assertCount(1, $transport->sent);
        self::assertSame([null, $at], [$first->deliveredAt(), $second->deliveredAt()]);
        self::assertSame(0, $first->attempts());
    }

    public function testAClaimLastsLongerThanARunAndLapses(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at);

        self::assertTrue($this->store->claim($message, $at, $at->modify(Courier::CLAIM)));
        self::assertFalse($this->store->claim($message, $at->modify('+29 minutes'), $at->modify('+59 minutes')));
        self::assertFalse($message->isDue($at->modify('+29 minutes')));
        self::assertTrue($message->isDue($at->modify('+30 minutes')));
        self::assertTrue($this->store->claim($message, $at->modify('+30 minutes'), $at->modify('+60 minutes')));
    }

    public function testTheCourierTriesAHundredMessagesByDefault(): void
    {
        $this->store->addPartner(Partners::reseller());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');

        for ($i = 0; $i < 101; ++$i) {
            $this->store->queue(OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at));
        }

        self::assertSame(100, new Courier($this->store, $this->store, new RecordingTransport(), Partners::ourKey()->toString(), 8)->deliver($at));
    }

    public function testAMessageToASuspendedPartnerDoesNotHoldTheOthers(): void
    {
        $suspended = Partners::reseller('suspended');
        $suspended->suspend();

        $this->store->addPartner($suspended);
        $this->store->addPartner(Partners::reseller());

        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->store->queue(OutgoingMessage::to($suspended, 'product.updated', [], $at));
        $this->store->queue(OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $at));

        self::assertSame(1, new Courier($this->store, $this->store, new RecordingTransport(), Partners::ourKey()->toString(), 8)->deliver($at));
    }

    public function testTheCourierStopsAtItsLimit(): void
    {
        $this->store->addPartner(Partners::reseller());
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $publisher = new Publisher($this->store, $this->store, $this->store);
        $publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 1, true, $at);
        $publisher->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 2, true, $at);

        self::assertSame(1, new Courier($this->store, $this->store, new RecordingTransport(), Partners::ourKey()->toString(), 8)->deliver($at, 1));
    }

    public function testAMessageToASuspendedPartnerWaits(): void
    {
        $reseller = Partners::reseller();
        $this->store->addPartner($reseller);
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        new Publisher($this->store, $this->store, $this->store)->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 1, true, $at);
        $reseller->suspend();
        $transport = new RecordingTransport();

        self::assertSame(0, new Courier($this->store, $this->store, $transport, Partners::ourKey()->toString(), 1)->deliver($at));

        self::assertSame([], $transport->sent);
        self::assertSame(['The partner is unknown or suspended.', null], [$this->store->outbox[0]->lastError(), $this->store->outbox[0]->nextAttemptAt()]);
    }

    public function testTheLedgerSumsAndSettlesOneCurrency(): void
    {
        $supplier = Partners::supplier();
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->store->record(LedgerEntry::of($supplier, 'A-1', Money::of(-700, Currency::of('CAD')), $at));
        $this->store->record(LedgerEntry::of($supplier, 'A-2', Money::of(200, Currency::of('CAD')), $at));
        $this->store->record(LedgerEntry::of($supplier, 'A-3', Money::of(900, Currency::of('USD')), $at));
        $this->store->record(LedgerEntry::of(Partners::supplier('other'), 'A-4', Money::of(50, Currency::of('CAD')), $at));

        $ledger = new Ledger($this->store);

        self::assertSame(-500, $ledger->balance('supplier', Currency::of('CAD'))->minorUnits);
        self::assertSame(2, $ledger->settle('supplier', Currency::of('CAD'), $at));
        self::assertSame(0, $ledger->balance('supplier', Currency::of('CAD'))->minorUnits);
        self::assertSame(900, $ledger->balance('supplier', Currency::of('USD'))->minorUnits);
    }
}
