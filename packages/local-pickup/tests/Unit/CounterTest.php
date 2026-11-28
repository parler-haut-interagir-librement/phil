<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\Counter;
use Phil\LocalPickup\Exception\CodeLocked;
use Phil\LocalPickup\Exception\CodeRefused;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Exception\UnknownPickup;
use Phil\LocalPickup\HandOver;
use Phil\LocalPickup\HandOverRequest;
use Phil\LocalPickup\Memory\InMemoryHandOverLog;
use Phil\LocalPickup\Memory\InMemoryPickups;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupCodes;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Counter::class)]
#[CoversClass(HandOver::class)]
#[CoversClass(HandOverRequest::class)]
#[CoversClass(CodeLocked::class)]
#[CoversClass(CodeRefused::class)]
#[CoversClass(UnknownPickup::class)]
#[CoversClass(PickupRefused::class)]
final class CounterTest extends TestCase
{
    private InMemoryPickups $store;

    private InMemoryHandOverLog $log;

    private PickupCodes $codes;

    protected function setUp(): void
    {
        $this->store = new InMemoryPickups();
        $this->log = new InMemoryHandOverLog();
        $this->codes = new PickupCodes(Fixture::SECRET);
    }

    public function testTheRightCodeHandsTheOrderOverOnce(): void
    {
        $code = $this->readyWithCode();
        $counter = $this->counter();

        $handOver = $counter->handOver(new HandOverRequest('WEB-1', $code, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));

        self::assertSame('WEB-1', $handOver->order);
        self::assertSame('customer-7', $handOver->customer);
        self::assertSame('store-main', $handOver->point);
        self::assertSame('counter-1', $handOver->station);
        self::assertSame('staff-3', $handOver->staff);
        self::assertSame(Fixture::at('2026-11-24 14:05')->getTimestamp(), $handOver->at->getTimestamp());
        self::assertNull($handOver->collector);
        self::assertNull($handOver->designated, 'The designated name is kept only when someone else collects.');
        self::assertFalse($handOver->byThirdParty());
        self::assertSame([$handOver], $this->log->all());

        $pickup = $this->store->find('WEB-1');
        self::assertNotNull($pickup);
        self::assertSame(PickupState::PickedUp, $pickup->state);
        self::assertNull($pickup->codeDigest);

        try {
            $counter->handOver(new HandOverRequest('WEB-1', $code, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:06')));
            self::fail('A code is spent once used.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('state_picked_up', $pickupRefused->reason);
        }

        self::assertCount(1, $this->log->all());
    }

    public function testAThirdPartyCollectingIsJournalledWithTheDesignatedName(): void
    {
        $code = $this->readyWithCode('Élise Tremblay');

        $handOver = $this->counter()->handOver(new HandOverRequest('WEB-1', $code, 'register-2', 'staff-3', Fixture::at('2026-11-24 14:05'), 'Élise T.'));

        self::assertTrue($handOver->byThirdParty());
        self::assertSame('Élise T.', $handOver->collector);
        self::assertSame('Élise Tremblay', $handOver->designated);
        self::assertSame([$handOver], $this->log->all());
    }

    public function testAScannedCodeHandsTheOrderOver(): void
    {
        $code = $this->readyWithCode();

        $handOver = $this->counter()->handOver(HandOverRequest::scanned('WEB-1:'.$code, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05'), 'Sam'));

        self::assertSame('WEB-1', $handOver->order);
        self::assertSame('Sam', $handOver->collector);
    }

    public function testWrongCodesAreCountedThenLockTheCode(): void
    {
        $code = $this->readyWithCode();
        $counter = $this->counter(maxAttempts: 3);
        $wrong = '0000-0000' === $code ? '1111-1111' : '0000-0000';

        foreach ([2, 1] as $left) {
            try {
                $counter->handOver(new HandOverRequest('WEB-1', $wrong, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
                self::fail('A wrong code is refused.');
            } catch (CodeRefused $refused) {
                self::assertSame($left, $refused->attemptsLeft);
                self::assertSame(\sprintf('The code given for the order "WEB-1" is not its pickup code; %d attempts left.', $left), $refused->getMessage());
            }
        }

        try {
            $counter->handOver(new HandOverRequest('WEB-1', $wrong, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
            self::fail('The last wrong attempt locks the code.');
        } catch (CodeLocked $codeLocked) {
            self::assertSame('The pickup code of the order "WEB-1" is locked after too many wrong attempts; send a new one.', $codeLocked->getMessage());
        }

        self::assertSame(3, $this->store->find('WEB-1')?->failedAttempts);
        $this->expectException(CodeLocked::class);

        $counter->handOver(new HandOverRequest('WEB-1', $code, 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:06')));
    }

    public function testTheDefaultLimitIsFiveAttempts(): void
    {
        $this->readyWithCode();
        $counter = $this->counter();
        $refusals = 0;

        for ($i = 0; $i < 4; ++$i) {
            try {
                $counter->handOver(new HandOverRequest('WEB-1', 'not-a-code', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
            } catch (CodeRefused) {
                ++$refusals;
            }
        }

        self::assertSame(4, $refusals);
        $this->expectException(CodeLocked::class);

        $counter->handOver(new HandOverRequest('WEB-1', 'not-a-code', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
    }

    public function testAReadyPickupWithoutCodeIsRefused(): void
    {
        $this->store->save($this->ready());

        try {
            $this->counter()->handOver(new HandOverRequest('WEB-1', '7KQ2M91X', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
            self::fail('A pickup without code is not handed over.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('no_code', $pickupRefused->reason);
        }

        self::assertSame(0, $this->store->find('WEB-1')?->failedAttempts);
    }

    public function testAPickupBeingPreparedIsRefused(): void
    {
        $this->store->save(Pickup::place('WEB-1', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00')));

        $this->expectException(PickupRefused::class);
        $this->expectExceptionMessageIsOrContains('is preparing: it cannot be handed over.');

        $this->counter()->handOver(new HandOverRequest('WEB-1', '7KQ2M91X', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
    }

    public function testAnUnknownOrderIsReported(): void
    {
        $this->expectException(UnknownPickup::class);

        $this->counter()->handOver(new HandOverRequest('WEB-9', '7KQ2M91X', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')));
    }

    public function testTheAttemptLimitIsBounded(): void
    {
        self::assertInstanceOf(Counter::class, $this->counter(maxAttempts: 1));
        self::assertInstanceOf(Counter::class, $this->counter(maxAttempts: 20));
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The attempt limit is between 1 and 20.');

        $this->counter(maxAttempts: 21);
    }

    public function testARequestChecksWhatItCarries(): void
    {
        $request = new HandOverRequest('WEB-1', '7KQ2M91X', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05'));

        self::assertSame(['order' => 'WEB-1', 'code' => '********', 'station' => 'counter-1', 'staff' => 'staff-3', 'at' => $request->at], $request->__debugInfo());
        self::assertNull($request->collector);

        foreach ([
            static fn (): HandOverRequest => new HandOverRequest('a b', 'x', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')),
            static fn (): HandOverRequest => new HandOverRequest('WEB-1', 'x', '', 'staff-3', Fixture::at('2026-11-24 14:05')),
            static fn (): HandOverRequest => new HandOverRequest('WEB-1', 'x', 'counter-1', 'a b', Fixture::at('2026-11-24 14:05')),
            static fn (): HandOverRequest => new HandOverRequest('WEB-1', 'x', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05'), ' '),
        ] as $index => $build) {
            try {
                $build();
                self::fail(\sprintf('Request %d is refused.', $index));
            } catch (InvalidPickup) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAHandOverWithoutNamesKeepsTheRest(): void
    {
        $handOver = new HandOver('WEB-1', 'customer-7', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05'), 'Sam', 'Élise');
        $erased = $handOver->withoutNames();

        self::assertEquals(new HandOver('WEB-1', 'customer-7', 'store-main', 'counter-1', 'staff-3', Fixture::at('2026-11-24 14:05')), $erased);
        self::assertFalse($erased->byThirdParty());
    }

    private function counter(int $maxAttempts = Counter::DEFAULT_MAX_ATTEMPTS): Counter
    {
        return new Counter($this->store, $this->codes, $this->log, $maxAttempts);
    }

    private function ready(?string $collector = null): Pickup
    {
        $pickup = Pickup::place('WEB-1', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), $collector);
        $pickup->ready(Fixture::at('2026-11-24 12:00'));

        return $pickup;
    }

    private function readyWithCode(?string $collector = null): string
    {
        $pickup = $this->ready($collector);
        $issued = $this->codes->issue('WEB-1');
        $pickup->issueCode($issued->digest);
        $this->store->save($pickup);

        return $issued->display();
    }
}
