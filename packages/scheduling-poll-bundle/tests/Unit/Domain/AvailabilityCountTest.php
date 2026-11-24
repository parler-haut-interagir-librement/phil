<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Domain\Tally\SlotTally;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(AvailabilityCount::class)]
#[CoversClass(SlotTally::class)]
final class AvailabilityCountTest extends TestCase
{
    private const string POLL = '0195a6c4-0000-7000-8000-000000000001';

    public function testTheMostYesAnswersWinThenTheMostIfNeedBeAnswersThenTheEarliestDate(): void
    {
        [$a, $b, $c, $d] = $this->slots(4);
        $slots = [$a, $b, $c, $d];
        $tallies = new AvailabilityCount()->count($slots, [
            $this->respondent([$a->id => 'yes', $b->id => 'yes', $c->id => 'if_need_be', $d->id => 'yes']),
            $this->respondent([$a->id => 'if_need_be', $b->id => 'yes', $c->id => 'yes', $d->id => 'yes']),
            $this->respondent([$a->id => 'no', $b->id => 'if_need_be', $c->id => 'yes', $d->id => 'no']),
        ]);

        self::assertSame([$b->id, $c->id, $d->id, $a->id], array_map(static fn (SlotTally $tally): string => $tally->slot->id, $tallies));
        self::assertSame([[2, 1, 0, 1], [2, 1, 0, 1], [2, 0, 1, 3], [1, 1, 1, 4]], array_map(static fn (SlotTally $tally): array => [$tally->yes, $tally->ifNeedBe, $tally->no, $tally->place], $tallies));
        self::assertSame($b, AvailabilityCount::best($tallies));
    }

    public function testRevokedRespondentsAndThoseWhoHaveNotAnsweredAreNotCounted(): void
    {
        [$a, $b] = $this->slots(2);
        $slots = [$a, $b];
        $revoked = $this->respondent([$a->id => 'yes', $b->id => 'yes']);
        $revoked->revoke(new DateTimeImmutable('2026-11-22'));

        $tallies = new AvailabilityCount()->count($slots, [$revoked, $this->respondent(null), $this->respondent([$a->id => 'no', $b->id => 'if_need_be'])]);

        self::assertSame([$b->id, $a->id], array_map(static fn (SlotTally $tally): string => $tally->slot->id, $tallies));
        self::assertSame([0, 1, 0, 1], [$tallies[0]->yes, $tallies[0]->ifNeedBe, $tallies[0]->no, $tallies[0]->place]);
        self::assertSame([0, 0, 1, 2], [$tallies[1]->yes, $tallies[1]->ifNeedBe, $tallies[1]->no, $tallies[1]->place]);
        self::assertTrue($tallies[0]->hasAvailability());
        self::assertFalse($tallies[1]->hasAvailability());
    }

    public function testNobodyAvailableChoosesNoDate(): void
    {
        [$a, $b] = $this->slots(2);
        $slots = [$a, $b];
        $tallies = new AvailabilityCount()->count($slots, [$this->respondent([$a->id => 'no', $b->id => 'no'])]);

        self::assertSame([1, 1], [$tallies[0]->place, $tallies[1]->place]);
        self::assertSame($a, $tallies[0]->slot);
        self::assertNull(AvailabilityCount::best($tallies));
        self::assertNull(AvailabilityCount::best([]));
        self::assertNull(AvailabilityCount::best(new AvailabilityCount()->count($slots, [])));
    }

    public function testWithoutAnswersEveryDateCountsNothing(): void
    {
        [$a, $b] = $this->slots(2);
        $tallies = new AvailabilityCount()->count([$a, $b], []);

        self::assertSame([[0, 0, 0, 1], [0, 0, 0, 1]], array_map(static fn (SlotTally $tally): array => [$tally->yes, $tally->ifNeedBe, $tally->no, $tally->place], $tallies));

        $answered = new AvailabilityCount()->count([$a, $b], [$this->respondent([$a->id => 'no', $b->id => 'no'])]);

        self::assertSame([[0, 0, 1], [0, 0, 1]], array_map(static fn (SlotTally $tally): array => [$tally->yes, $tally->ifNeedBe, $tally->no], $answered));
    }

    /**
     * @return list<Slot> one a day from 1 December, in the order given, the later ones first in the list
     */
    private function slots(int $count): array
    {
        $slots = [];

        for ($day = $count; $day >= 1; --$day) {
            $start = new DateTimeImmutable(\sprintf('2026-12-%02d 19:00 UTC', $day));
            $slots[] = new Slot(Uuid::v7()->toRfc4122(), self::POLL, $count - $day, $start, $start->modify('+1 hour'));
        }

        return array_reverse($slots);
    }

    /**
     * @param array<string, string>|null $answers
     */
    private function respondent(?array $answers): Respondent
    {
        $respondent = new Respondent(Uuid::v7()->toRfc4122(), self::POLL, bin2hex(random_bytes(16)), 'Someone', new DateTimeImmutable('2026-11-21'));

        if (null !== $answers) {
            $respondent->answer(array_map(Availability::from(...), $answers), null, new DateTimeImmutable('2026-11-21'));
        }

        return $respondent;
    }
}
