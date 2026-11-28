<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit\Message;

use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Message\NotifyPickupReady;
use Phil\LocalPickup\Message\NotifyPickupReadyHandler;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\ReadyNotice;
use Phil\LocalPickup\Tests\Support\Fixture;
use Phil\LocalPickup\Tests\Support\Recorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(NotifyPickupReadyHandler::class)]
#[CoversClass(NotifyPickupReady::class)]
#[CoversClass(ReadyNotice::class)]
final class NotifyPickupReadyHandlerTest extends TestCase
{
    public function testTheWorkerReadsThePickupIssuesItsCodeAndSendsTheNotice(): void
    {
        [$store, $recorder, $handler, $codes] = $this->setUpReady('Élise Tremblay');

        $handler(new NotifyPickupReady('WEB-1'));

        self::assertCount(1, $recorder->notices);
        $notice = $recorder->notices[0];
        self::assertSame('WEB-1', $notice->order);
        self::assertSame('customer-7', $notice->customer);
        self::assertSame('Main store', $notice->point->name);
        self::assertSame(Fixture::at('2026-11-24 14:00')->getTimestamp(), $notice->slot->from);
        self::assertSame(Fixture::at('2026-11-26 14:00')->getTimestamp(), $notice->holdUntil->getTimestamp());
        self::assertSame('Élise Tremblay', $notice->collector);

        $pickup = $store->find('WEB-1');
        self::assertNotNull($pickup);
        self::assertTrue($pickup->notified);
        self::assertSame($notice->code->digest, $pickup->codeDigest);
        self::assertTrue($codes->matches('WEB-1', $notice->code->code, $pickup->codeDigest));
    }

    public function testAMessageDeliveredTwiceSendsOneNotice(): void
    {
        [$store, $recorder, $handler] = $this->setUpReady();

        $handler(new NotifyPickupReady('WEB-1'));
        $digest = $store->find('WEB-1')?->codeDigest;
        $handler(new NotifyPickupReady('WEB-1'));

        self::assertCount(1, $recorder->notices);
        self::assertSame($digest, $store->find('WEB-1')?->codeDigest);
    }

    public function testANoticeThatFailsIsSentAgainWithANewCode(): void
    {
        [$store, $recorder, $handler] = $this->setUpReady();
        $recorder->failNotice = true;

        try {
            $handler(new NotifyPickupReady('WEB-1'));
            self::fail('The failure reaches the bus, which retries.');
        } catch (RuntimeException) {
            $first = $store->find('WEB-1');
            self::assertNotNull($first?->codeDigest, 'The digest is saved before the notice is sent.');
            self::assertFalse($first->notified);
        }

        $recorder->failNotice = false;
        $handler(new NotifyPickupReady('WEB-1'));

        self::assertCount(1, $recorder->notices);
        $second = $store->find('WEB-1');
        self::assertNotNull($second);
        self::assertSame($recorder->notices[0]->code->digest, $second->codeDigest);
        self::assertNotSame($first->codeDigest, $second->codeDigest);
    }

    public function testNothingIsSentForAnUnknownOrNotReadyPickup(): void
    {
        $store = new InMemoryPickups();
        $recorder = new Recorder();
        $handler = new NotifyPickupReadyHandler($store, new PickupCodes(Fixture::SECRET), $recorder);
        $store->save(Pickup::place('WEB-2', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00')));
        $picked = Pickup::place('WEB-3', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'));
        $picked->ready(Fixture::at('2026-11-24 12:00'));
        $picked->pickUp(Fixture::at('2026-11-24 14:00'));

        $store->save($picked);

        $handler(new NotifyPickupReady('WEB-1'));
        $handler(new NotifyPickupReady('WEB-2'));
        $handler(new NotifyPickupReady('WEB-3'));

        self::assertSame([], $recorder->notices);
        self::assertNull($store->find('WEB-2')?->codeDigest);
    }

    /**
     * @return array{InMemoryPickups, Recorder, NotifyPickupReadyHandler, PickupCodes}
     */
    private function setUpReady(?string $collector = null): array
    {
        $store = new InMemoryPickups();
        $recorder = new Recorder();
        $codes = new PickupCodes(Fixture::SECRET);
        $pickup = Pickup::place('WEB-1', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), $collector);
        $pickup->ready(Fixture::at('2026-11-24 12:00'));

        $store->save($pickup);

        return [$store, $recorder, new NotifyPickupReadyHandler($store, $codes, $recorder), $codes];
    }
}
