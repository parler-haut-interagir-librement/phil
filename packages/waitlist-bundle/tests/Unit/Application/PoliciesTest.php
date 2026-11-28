<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\WaitlistBundle\Application\Policy\FirstInLine;
use Phil\WaitlistBundle\Application\Policy\FirstThatFits;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\WaitlistMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FirstInLine::class)]
#[CoversClass(FirstThatFits::class)]
final class PoliciesTest extends TestCase
{
    /**
     * @return list<Entry>
     */
    private function entries(int ...$places): array
    {
        $now = new DateTimeImmutable('2026-11-01 12:00:00 UTC');
        $waitlist = Waitlist::open('session', 'S-1', WaitlistMode::Queue, 3_600, $now);

        return array_values(array_map(static fn (int $count): Entry => Entry::join($waitlist, 'account-'.$count, $count, $now), $places));
    }

    /**
     * @param list<Entry> $picked
     *
     * @return list<int>
     */
    private function places(array $picked): array
    {
        return array_map(static fn (Entry $entry): int => $entry->places(), $picked);
    }

    public function testFirstInLineStopsAtTheFirstThatDoesNotFit(): void
    {
        $policy = new FirstInLine();

        self::assertSame([1, 2], $this->places($policy->pick(3, $this->entries(1, 2, 4, 1))));
        self::assertSame([1, 2], $this->places($policy->pick(4, $this->entries(1, 2, 4, 1))));
        self::assertSame([], $this->places($policy->pick(3, $this->entries(4, 1))));
        self::assertSame([3], $this->places($policy->pick(3, $this->entries(3))));
        self::assertSame([], $policy->pick(3, []));
    }

    public function testFirstThatFitsPassesOverThoseThatNeedMore(): void
    {
        $policy = new FirstThatFits();

        self::assertSame([1, 2, 1], $this->places($policy->pick(4, $this->entries(1, 2, 4, 1))));
        self::assertSame([1], $this->places($policy->pick(3, $this->entries(4, 1))));
        self::assertSame([3], $this->places($policy->pick(3, $this->entries(3, 1))));
        self::assertSame([], $policy->pick(3, $this->entries(4, 5)));
    }
}
