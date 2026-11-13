<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Restriction;
use PHPUnit\Framework\TestCase;

/**
 * What every store of the activations does, run against each one.
 */
abstract class ActivationStoreTestCase extends TestCase
{
    abstract protected function store(): ActivationStore;

    public function testAnActivationIsKeptAsAdded(): void
    {
        $store = $this->store();
        $activation = $this->activation('a', '10:00', '12:00', 'm-1', ['signups', 'invitations'], 'Wave');
        $store->add($activation);

        $found = $store->find($activation->id);

        self::assertNotNull($found);
        self::assertSame($activation->id, $found->id);
        self::assertSame('suspend-signups', $found->preset);
        self::assertSame(['signups', 'invitations'], $found->restrictionNames());
        self::assertSame('2026-11-13T10:00:00+00:00', $found->startedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T12:00:00+00:00', $found->endsAt->format(\DATE_ATOM));
        self::assertSame('m-1', $found->startedBy?->id);
        self::assertSame('Wave', $found->reason);
        self::assertNull($found->liftedAt);
        self::assertNull($found->liftedBy);
        self::assertNull($found->liftReason);
        self::assertNull($store->find(str_repeat('f', 32)));
    }

    public function testTheMomentsAreTheSameInstantsWhateverZoneTheyWereGivenIn(): void
    {
        $store = $this->store();
        $activation = new Activation(
            str_repeat('b', 32),
            'read-only',
            [Restriction::named('read-only')],
            new DateTimeImmutable('2026-11-13 05:00:00', new DateTimeZone('America/Toronto')),
            new DateTimeImmutable('2026-11-13 07:00:00', new DateTimeZone('America/Toronto')),
            null,
        );
        $store->add($activation);

        self::assertSame(1_794_564_000, $this->found($store, $activation->id)->startedAt->getTimestamp());
    }

    public function testTheActivationsAheadAreThoseNotLiftedWhoseEndIsAfter(): void
    {
        $store = $this->store();
        $store->add($late = $this->activation('c', '11:00', '13:00'));
        $store->add($early = $this->activation('a', '09:00', '12:00'));
        $store->add($this->activation('d', '08:00', '10:00'));
        $store->add($lifted = $this->activation('e', '09:30', '15:00'));
        self::assertTrue($store->lift($lifted->id, $this->at('10:30'), null, LiftReason::Manual));

        self::assertSame([$early->id, $late->id], $this->ids($store->unliftedEndingAfter($this->at('10:00'))));
        self::assertSame([$late->id], $this->ids($store->unliftedEndingAfter($this->at('12:00'))));
    }

    public function testTheDueActivationsAreThoseNotLiftedPastTheirEnd(): void
    {
        $store = $this->store();
        $store->add($second = $this->activation('a', '08:00', '10:30'));
        $store->add($first = $this->activation('b', '09:00', '10:00'));
        $store->add($third = $this->activation('c', '07:00', '11:00'));
        $store->add($this->activation('d', '07:00', '11:01'));
        $store->add($lifted = $this->activation('e', '07:00', '09:00'));
        $store->lift($lifted->id, $this->at('08:00'), null, LiftReason::Manual);

        self::assertSame([$first->id, $second->id, $third->id], $store->dueIdentifiers($this->at('11:00'), 10));
        self::assertSame([$first->id, $second->id], $store->dueIdentifiers($this->at('11:00'), 2));
    }

    public function testAnActivationIsLiftedOnce(): void
    {
        $store = $this->store();
        $store->add($activation = $this->activation('a', '10:00', '12:00'));

        self::assertTrue($store->lift($activation->id, $this->at('11:00'), Actor::of('m-2'), LiftReason::Manual));
        self::assertFalse($store->lift($activation->id, $this->at('11:30'), null, LiftReason::Expired));
        self::assertFalse($store->lift(str_repeat('f', 32), $this->at('11:30'), null, LiftReason::Expired));

        $found = $store->find($activation->id);

        self::assertSame('2026-11-13T11:00:00+00:00', $found?->liftedAt?->format(\DATE_ATOM));
        self::assertSame('m-2', $found->liftedBy?->id);
        self::assertSame(LiftReason::Manual, $found->liftReason);
    }

    public function testAnActivationIsExtendedWhileNotLifted(): void
    {
        $store = $this->store();
        $store->add($activation = $this->activation('a', '10:00', '12:00'));

        self::assertTrue($store->extend($activation->id, $this->at('14:00')));
        self::assertSame('2026-11-13T14:00:00+00:00', $this->found($store, $activation->id)->endsAt->format(\DATE_ATOM));

        $store->lift($activation->id, $this->at('13:00'), null, LiftReason::Manual);

        self::assertFalse($store->extend($activation->id, $this->at('15:00')));
        self::assertFalse($store->extend(str_repeat('f', 32), $this->at('15:00')));
        self::assertSame('2026-11-13T14:00:00+00:00', $this->found($store, $activation->id)->endsAt->format(\DATE_ATOM));
    }

    public function testTheHistoryIsTheLatestStartFirst(): void
    {
        $store = $this->store();
        $store->add($middle = $this->activation('a', '10:00', '12:00'));
        $store->add($latest = $this->activation('b', '11:00', '12:00'));
        $store->add($earliest = $this->activation('c', '09:00', '12:00'));

        self::assertSame([$latest->id, $middle->id, $earliest->id], $this->ids($store->history(10)));
        self::assertSame([$latest->id, $middle->id], $this->ids($store->history(2)));
        self::assertSame([$middle->id, $earliest->id], $this->ids($store->history(5, 1)));
    }

    public function testAnActorIsFoundAndForgotten(): void
    {
        $store = $this->store();
        $store->add($started = $this->activation('a', '10:00', '12:00', 'm-1'));
        $store->add($lifted = $this->activation('b', '11:00', '12:00', 'm-2'));
        $store->add($other = $this->activation('c', '09:00', '12:00', 'm-3'));
        $store->lift($lifted->id, $this->at('11:30'), Actor::of('m-1'), LiftReason::Manual);

        self::assertSame([$lifted->id, $started->id], $this->ids($store->ofActor(Actor::of('m-1'))));
        self::assertSame(2, $store->forgetActor(Actor::of('m-1')));
        self::assertSame([], $store->ofActor(Actor::of('m-1')));
        self::assertNull($this->found($store, $started->id)->startedBy);
        self::assertSame('m-2', $this->found($store, $lifted->id)->startedBy?->id);
        self::assertNull($this->found($store, $lifted->id)->liftedBy);
        self::assertSame(LiftReason::Manual, $this->found($store, $lifted->id)->liftReason);
        self::assertSame('m-3', $this->found($store, $other->id)->startedBy?->id);
        self::assertSame(0, $store->forgetActor(Actor::of('m-1')));
    }

    public function testTheListsAreListsWhateverIsLeftOutOfThem(): void
    {
        $store = $this->store();
        $store->add($this->activation('a', '12:00', '14:00', 'm-2'));
        $store->add($this->activation('b', '11:00', '13:00', 'm-1'));
        $store->add($this->activation('c', '10:00', '15:00', 'm-1'));

        self::assertList($store->ofActor(Actor::of('m-1')));
        self::assertSame(['b', 'c'], array_map(static fn (Activation $activation): string => $activation->id[0], $store->ofActor(Actor::of('m-1'))));
        self::assertList($store->history(10));
        self::assertList($store->unliftedEndingAfter($this->at('11:30')));
        self::assertList($store->dueIdentifiers($this->at('20:00'), 10));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    protected static function assertList(array $values): void
    {
        self::assertTrue(array_is_list($values));
    }

    protected function found(ActivationStore $store, string $id): Activation
    {
        $activation = $store->find($id);
        self::assertInstanceOf(Activation::class, $activation);

        return $activation;
    }

    protected function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 '.$time.':00 UTC');
    }

    /**
     * @param list<string> $restrictions
     */
    protected function activation(string $letter, string $start, string $end, ?string $actor = null, array $restrictions = ['signups'], ?string $reason = null): Activation
    {
        return new Activation(
            str_repeat($letter, 32),
            'suspend-signups',
            array_map(Restriction::named(...), $restrictions),
            $this->at($start),
            $this->at($end),
            null === $actor ? null : Actor::of($actor),
            $reason,
        );
    }

    /**
     * @param list<Activation> $activations
     *
     * @return list<string>
     */
    private function ids(array $activations): array
    {
        return array_map(static fn (Activation $activation): string => $activation->id, $activations);
    }
}
