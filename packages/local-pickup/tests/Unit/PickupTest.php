<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit;

use Phil\LocalPickup\ArrivalSource;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\PickupState;
use Phil\LocalPickup\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pickup::class)]
#[CoversClass(PickupState::class)]
#[CoversClass(PickupRefused::class)]
final class PickupTest extends TestCase
{
    private const string DIGEST = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testAPlacedPickupIsBeingPrepared(): void
    {
        $pickup = $this->placed('Élise Tremblay');

        self::assertSame('WEB-1', $pickup->order);
        self::assertSame('customer-7', $pickup->customer);
        self::assertSame('store-main', $pickup->point->code);
        self::assertSame(PickupState::Preparing, $pickup->state);
        self::assertSame('Élise Tremblay', $pickup->collector);
        self::assertSame(Fixture::at('2026-11-24 09:00')->getTimestamp(), $pickup->placedAt->getTimestamp());
        self::assertNull($pickup->readyAt);
        self::assertNull($pickup->holdUntil);
        self::assertNull($pickup->codeDigest);
        self::assertSame(0, $pickup->failedAttempts);
        self::assertFalse($pickup->notified);
        self::assertNull($pickup->arrivedAt);
        self::assertNull($pickup->arrival);
        self::assertNull($pickup->endedAt);
        self::assertTrue($pickup->state->holdsSlot());
    }

    public function testItRefusesACollectorNameOutOfShape(): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The collector name is a text');

        $this->placed('');
    }

    public function testItRefusesACustomerOutOfShape(): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The customer is 1 to 255 bytes');

        Pickup::place('WEB-1', 'a b', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'));
    }

    public function testItIsHeldFromItsSlotWhenReadyBeforeIt(): void
    {
        $pickup = $this->placed();
        $pickup->ready(Fixture::at('2026-11-24 12:00'));

        self::assertSame(PickupState::Ready, $pickup->state);
        self::assertTrue($pickup->state->holdsSlot());
        self::assertSame(Fixture::at('2026-11-24 12:00')->getTimestamp(), $pickup->readyAt?->getTimestamp());
        self::assertNotNull($pickup->holdUntil);
        self::assertSame(Fixture::at('2026-11-26 14:00')->getTimestamp(), $pickup->holdUntil->getTimestamp());
        self::assertSame('America/Toronto', $pickup->holdUntil->getTimezone()->getName());
        self::assertFalse($pickup->overdue(Fixture::at('2026-11-26 13:59:59')));
        self::assertTrue($pickup->overdue(Fixture::at('2026-11-26 14:00')));
    }

    public function testItIsHeldFromWhenItIsReadyAfterItsSlot(): void
    {
        $pickup = $this->placed();
        $pickup->ready(Fixture::at('2026-11-24 15:00'));

        self::assertSame(Fixture::at('2026-11-26 15:00')->getTimestamp(), $pickup->holdUntil?->getTimestamp());
    }

    public function testAPickupBeingPreparedIsNeverOverdue(): void
    {
        self::assertFalse($this->placed()->overdue(Fixture::at('2027-01-01')));
    }

    public function testItCannotBeMadeReadyTwice(): void
    {
        $pickup = $this->ready();

        try {
            $pickup->ready(Fixture::at('2026-11-24 13:00'));
            self::fail('A ready pickup is not made ready again.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('state_ready', $pickupRefused->reason);
            self::assertSame('The pickup of the order "WEB-1" is ready: it cannot be made ready.', $pickupRefused->getMessage());
            self::assertSame([], $pickupRefused->violations);
        }
    }

    public function testACodeIsKeptAsItsDigestAndItsAttemptsStartAgain(): void
    {
        $pickup = $this->ready();
        $pickup->issueCode(self::DIGEST);
        self::assertSame(1, $pickup->failAttempt());
        self::assertSame(2, $pickup->failAttempt());

        $pickup->issueCode(str_repeat('b', 64));
        self::assertSame(str_repeat('b', 64), $pickup->codeDigest);
        self::assertSame(0, $pickup->failedAttempts);

        $pickup->markNotified();
        self::assertTrue($pickup->notified);

        $pickup->failAttempt();
        $pickup->resetCode();
        self::assertNull($pickup->codeDigest);
        self::assertSame(0, $pickup->failedAttempts);
        self::assertFalse($pickup->notified);
    }

    public function testItRefusesADigestOutOfShape(): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The code digest is 64 lowercase hexadecimal digits.');

        $this->ready()->issueCode(strtoupper(self::DIGEST));
    }

    /**
     * @return iterable<string, array{callable(Pickup): void, string}>
     */
    public static function actionsOnlyWhileReady(): iterable
    {
        yield 'code' => [static function (Pickup $pickup): void { $pickup->issueCode(self::DIGEST); }, 'given a code'];
        yield 'notice' => [static function (Pickup $pickup): void { $pickup->markNotified(); }, 'notified'];
        yield 'new code' => [static function (Pickup $pickup): void { $pickup->resetCode(); }, 'given a new code'];
        yield 'attempt' => [static function (Pickup $pickup): void { $pickup->failAttempt(); }, 'tried'];
        yield 'pickup' => [static function (Pickup $pickup): void { $pickup->pickUp(Fixture::at('2026-11-24 14:00')); }, 'picked up'];
        yield 'expiry' => [static function (Pickup $pickup): void { $pickup->expire(Fixture::at('2026-12-24 14:00')); }, 'expired'];
    }

    /**
     * @param callable(Pickup): void $action
     */
    #[DataProvider('actionsOnlyWhileReady')]
    public function testItRefusesWhileBeingPrepared(callable $action, string $verb): void
    {
        $this->expectException(PickupRefused::class);
        $this->expectExceptionMessageIsOrContains('is preparing: it cannot be '.$verb.'.');

        $action($this->placed());
    }

    public function testAPickedUpPickupSpendsItsCode(): void
    {
        $pickup = $this->ready();
        $pickup->issueCode(self::DIGEST);
        $pickup->pickUp(Fixture::at('2026-11-24 14:10'));

        self::assertSame(PickupState::PickedUp, $pickup->state);
        self::assertFalse($pickup->state->holdsSlot());
        self::assertNull($pickup->codeDigest);
        self::assertSame(Fixture::at('2026-11-24 14:10')->getTimestamp(), $pickup->endedAt?->getTimestamp());
        self::assertFalse($pickup->overdue(Fixture::at('2027-01-01')));
    }

    public function testAnOverduePickupExpiresAndSpendsItsCode(): void
    {
        $pickup = $this->ready();
        $pickup->issueCode(self::DIGEST);
        $pickup->expire(Fixture::at('2026-11-26 14:00'));

        self::assertSame(PickupState::Expired, $pickup->state);
        self::assertFalse($pickup->state->holdsSlot());
        self::assertNull($pickup->codeDigest);
        self::assertSame(Fixture::at('2026-11-26 14:00')->getTimestamp(), $pickup->endedAt?->getTimestamp());
    }

    public function testAPickupWithinItsHoldDoesNotExpire(): void
    {
        $pickup = $this->ready();

        try {
            $pickup->expire(Fixture::at('2026-11-26 13:59:59'));
            self::fail('A held pickup does not expire.');
        } catch (PickupRefused $pickupRefused) {
            self::assertSame('hold_running', $pickupRefused->reason);
            self::assertSame(PickupState::Ready, $pickup->state);
        }
    }

    public function testOnlyTheFirstArrivalCounts(): void
    {
        $pickup = $this->placed();

        self::assertTrue($pickup->arrive(ArrivalSource::Automatic, Fixture::at('2026-11-24 13:58')));
        self::assertFalse($pickup->arrive(ArrivalSource::Manual, Fixture::at('2026-11-24 14:01')));
        self::assertSame(ArrivalSource::Automatic, $pickup->arrival);
        self::assertSame(Fixture::at('2026-11-24 13:58')->getTimestamp(), $pickup->arrivedAt?->getTimestamp());
    }

    public function testAReadyPickupTakesAnArrival(): void
    {
        $pickup = $this->ready();

        self::assertTrue($pickup->arrive(ArrivalSource::Manual, Fixture::at('2026-11-24 14:01')));
        self::assertSame(ArrivalSource::Manual, $pickup->arrival);
    }

    public function testACollectedPickupTakesNoArrival(): void
    {
        $pickup = $this->ready();
        $pickup->pickUp(Fixture::at('2026-11-24 14:10'));

        $this->expectException(PickupRefused::class);
        $this->expectExceptionMessageIsOrContains('is picked_up: it cannot be announced.');

        $pickup->arrive(ArrivalSource::Manual, Fixture::at('2026-11-24 14:11'));
    }

    public function testTheCollectorIsErased(): void
    {
        $pickup = $this->placed('Élise Tremblay');
        $pickup->eraseCollector();

        self::assertNull($pickup->collector);
    }

    public function testItIsReadBackFromItsArrayInEveryState(): void
    {
        $placed = $this->placed('Élise Tremblay');
        $ready = $this->ready();
        $ready->issueCode(self::DIGEST);
        $ready->failAttempt();
        $ready->markNotified();
        $ready->arrive(ArrivalSource::Manual, Fixture::at('2026-11-24 14:01'));

        $picked = $this->ready();
        $picked->issueCode(self::DIGEST);
        $picked->markNotified();
        $picked->failAttempt();
        $picked->pickUp(Fixture::at('2026-11-24 14:10'));

        $expired = $this->ready();
        $expired->markNotified();
        $expired->expire(Fixture::at('2026-11-27'));

        foreach ([$placed, $ready, $picked, $expired] as $pickup) {
            $read = Pickup::fromArray($pickup->toArray());
            self::assertSame($pickup->toArray(), $read->toArray());
            self::assertSame('America/Toronto', $read->placedAt->getTimezone()->getName());
            self::assertSame('America/Toronto', $read->slot->start->getTimezone()->getName());
        }

        self::assertSame([
            'order' => 'WEB-1',
            'customer' => 'customer-7',
            'point' => Fixture::point()->agreed()->toArray(),
            'slot' => [Fixture::at('2026-11-24 14:00')->getTimestamp(), Fixture::at('2026-11-24 14:30')->getTimestamp()],
            'placed_at' => Fixture::at('2026-11-24 09:00')->getTimestamp(),
            'state' => 'ready',
            'collector' => null,
            'ready_at' => Fixture::at('2026-11-24 12:00')->getTimestamp(),
            'hold_until' => Fixture::at('2026-11-26 14:00')->getTimestamp(),
            'code_digest' => self::DIGEST,
            'failed_attempts' => 1,
            'notified' => true,
            'arrived_at' => Fixture::at('2026-11-24 14:01')->getTimestamp(),
            'arrival' => 'manual',
            'ended_at' => null,
        ], $ready->toArray());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badRows(): iterable
    {
        $placed = Pickup::place('WEB-1', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'))->toArray();
        $readyPickup = Pickup::fromArray($placed);
        $readyPickup->ready(Fixture::at('2026-11-24 12:00'));

        $ready = $readyPickup->toArray();

        yield 'point missing' => [array_diff_key($placed, ['point' => true]), 'The pickup point of the pickup is an array.'];
        yield 'slot missing' => [array_diff_key($placed, ['slot' => true]), 'The slot of the pickup is a start and a later end'];
        yield 'slot keyed' => [['slot' => ['a' => 1, 'b' => 2]] + $placed, 'The slot of the pickup'];
        yield 'slot of one' => [['slot' => [1]] + $placed, 'The slot of the pickup'];
        yield 'slot of three' => [['slot' => [1, 2, 3]] + $placed, 'The slot of the pickup'];
        yield 'slot start as text' => [['slot' => ['1', 2]] + $placed, 'The slot of the pickup'];
        yield 'slot end as text' => [['slot' => [1, '2']] + $placed, 'The slot of the pickup'];
        yield 'slot backwards' => [['slot' => [2, 2]] + $placed, 'The slot of the pickup'];
        yield 'slot before the epoch' => [['slot' => [-1, 2]] + $placed, 'The slot of the pickup'];
        yield 'slot past the year 9999' => [['slot' => [1, Pickup::LAST_MOMENT + 1]] + $placed, 'The slot of the pickup'];
        yield 'unknown state' => [['state' => 'lost'] + $placed, 'The state of the pickup is one of PickupState.'];
        yield 'state missing' => [array_diff_key($placed, ['state' => true]), 'The field "state" of the pickup is a string.'];
        yield 'unknown arrival' => [['arrival' => 'teleport'] + $placed, 'The arrival of the pickup is one of ArrivalSource.'];
        yield 'arrival as number' => [['arrival' => 1] + $placed, 'The arrival of the pickup is one of ArrivalSource.'];
        yield 'arrival without moment' => [['arrival' => 'manual'] + $placed, 'The arrival of the pickup has both a moment and a source, or neither.'];
        yield 'moment without arrival' => [['arrived_at' => 1] + $placed, 'The arrival of the pickup has both'];
        yield 'collector as number' => [['collector' => 3] + $placed, 'The field "collector" of the pickup is a string or null.'];
        yield 'collector blank' => [['collector' => ' '] + $placed, 'The collector name is a text'];
        yield 'digest as number' => [['code_digest' => 3] + $ready, 'The field "code_digest" of the pickup is a string or null.'];
        yield 'digest out of shape' => [['code_digest' => 'abc'] + $ready, 'The code digest is 64'];
        yield 'attempts missing' => [array_diff_key($placed, ['failed_attempts' => true]), 'The failed attempts of the pickup is a natural number.'];
        yield 'attempts negative' => [['failed_attempts' => -1] + $placed, 'The failed attempts of the pickup is a natural number.'];
        yield 'notice as number' => [['notified' => 0] + $placed, 'The notice flag of the pickup is a boolean.'];
        yield 'moment as text' => [['ready_at' => '1'] + $ready, 'The field "ready_at" of the pickup is a timestamp or null.'];
        yield 'moment negative' => [['ready_at' => -1] + $ready, 'The field "ready_at" of the pickup is a timestamp or null.'];
        yield 'moment past the year 9999' => [['ready_at' => Pickup::LAST_MOMENT + 1] + $ready, 'The field "ready_at"'];
        yield 'placing missing' => [['placed_at' => null] + $placed, 'The placing moment of the pickup is a timestamp.'];
        yield 'order out of shape' => [['order' => 'a b'] + $placed, 'The order number is'];
        yield 'customer out of shape' => [['customer' => ''] + $placed, 'The customer is'];
        yield 'customer missing' => [array_diff_key($placed, ['customer' => true]), 'The field "customer" of the pickup is a string.'];
        yield 'preparing with a ready moment' => [['ready_at' => 1] + $placed, 'has a ready moment and a hold end once it is ready, and only then'];
        yield 'preparing with a hold' => [['hold_until' => 1] + $placed, 'has a ready moment and a hold end once it is ready'];
        yield 'ready without hold' => [['hold_until' => null] + $ready, 'has a ready moment and a hold end once it is ready'];
        yield 'ready without ready moment' => [['ready_at' => null] + $ready, 'has a ready moment and a hold end once it is ready'];
        yield 'ready with an end' => [['ended_at' => 1] + $ready, 'has an end moment once it is picked up or expired, and only then'];
        yield 'collected without end' => [['state' => 'picked_up'] + $ready, 'has an end moment once it is picked up or expired'];
        yield 'collected with a code' => [['state' => 'picked_up', 'ended_at' => 1, 'code_digest' => self::DIGEST] + $ready, 'has a code only while it is ready'];
        yield 'expired with a code' => [['state' => 'expired', 'ended_at' => 1, 'code_digest' => self::DIGEST] + $ready, 'has a code only while it is ready'];
        yield 'preparing with a code' => [['code_digest' => self::DIGEST] + $placed, 'has a code only while it is ready'];
        yield 'preparing with a notice' => [['notified' => true] + $placed, 'has a notice or attempts only once it is ready'];
        yield 'preparing with attempts' => [['failed_attempts' => 1] + $placed, 'has a notice or attempts only once it is ready'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('badRows')]
    public function testItRefusesARowOutOfShape(array $row, string $message): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains($message);

        Pickup::fromArray($row);
    }

    public function testItReadsTheBoundsOfAStoredRow(): void
    {
        $row = ['slot' => [0, Pickup::LAST_MOMENT], 'placed_at' => 0] + $this->placed()->toArray();
        $pickup = Pickup::fromArray($row);

        self::assertSame(0, $pickup->slot->from);
        self::assertSame(Pickup::LAST_MOMENT, $pickup->slot->until);
        self::assertSame(0, $pickup->placedAt->getTimestamp());
    }

    private function placed(?string $collector = null): Pickup
    {
        return Pickup::place('WEB-1', 'customer-7', Fixture::point()->agreed(), Fixture::slot('2026-11-24 14:00'), Fixture::at('2026-11-24 09:00'), $collector);
    }

    private function ready(): Pickup
    {
        $pickup = $this->placed();
        $pickup->ready(Fixture::at('2026-11-24 12:00'));

        return $pickup;
    }
}
