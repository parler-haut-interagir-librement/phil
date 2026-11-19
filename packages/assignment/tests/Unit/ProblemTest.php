<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Closure;
use Phil\Assignment\Applicant;
use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Limits;
use Phil\Assignment\Pair;
use Phil\Assignment\Problem;
use Phil\Assignment\Slot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Problem::class)]
final class ProblemTest extends TestCase
{
    public function testSlotsAndApplicantsAreKeptInIdentifierOrder(): void
    {
        $problem = new Problem(
            [new Slot('t2', 2), new Slot('t10', 2), new Slot('t1', 2)],
            [Applicant::person('cy', ['t2']), Applicant::person('ana', ['t1']), Applicant::group('bo', ['bo', 'dee'], ['t10'])],
            [new Pair('ana', 't2')],
            [new Pair('cy', 't1'), new Pair('cy', 't1')],
        );

        self::assertSame(['t1', 't10', 't2'], array_map(static fn (Slot $slot): string => $slot->id, $problem->slots));
        self::assertSame(['ana', 'bo', 'cy'], array_map(static fn (Applicant $applicant): string => $applicant->id, $problem->applicants));
        self::assertEquals([new Pair('ana', 't2')], $problem->forbidden);
        self::assertEquals([new Pair('cy', 't1')], $problem->forced);
        self::assertSame('t1', $problem->forcedSlot('cy'));
        self::assertNull($problem->forcedSlot('ana'));
        self::assertSame(['t1', 't10', 't2'], $problem->slotIds());
        self::assertSame(['ana', 'bo', 'cy'], $problem->applicantIds());
        self::assertTrue($problem->isForbidden('ana', 't2'));
        self::assertFalse($problem->isForbidden('ana', 't1'));
        self::assertFalse($problem->isForbidden('bo', 't2'));
        self::assertSame('t10', $problem->slot('t10')->id);
        self::assertSame('bo', $problem->applicant('bo')->id);
    }

    public function testForcedApplicantsAreSortedAndFillASlotExactly(): void
    {
        $problem = new Problem(
            [new Slot('t1', 3)],
            [Applicant::person('cy', []), Applicant::group('ana', ['ana', 'bo'], [])],
            [],
            [new Pair('cy', 't1'), new Pair('ana', 't1')],
        );

        self::assertEquals([new Pair('ana', 't1'), new Pair('cy', 't1')], $problem->forced);
    }

    public function testTheLimitsAreReachedButNotPassed(): void
    {
        $limits = new Limits(slots: 2, applicants: 2, choices: 2, members: 2);
        $problem = new Problem(
            [new Slot('t1', 2), new Slot('t2', 2)],
            [Applicant::person('ana', ['t1', 't2']), Applicant::group('g', ['bo', 'cy'], ['t1', 't2'])],
            limits: $limits,
        );

        self::assertCount(2, $problem->applicants);
    }

    /**
     * @return iterable<string, array{Closure(): Problem, string}>
     */
    public static function invalidProblems(): iterable
    {
        $limits = new Limits(slots: 1, applicants: 1, choices: 1, members: 1);

        yield 'too many slots' => [static fn (): Problem => new Problem([new Slot('a', 1), new Slot('b', 1)], [], limits: $limits), 'A problem has at most 1 slots.'];
        yield 'too many applicants' => [static fn (): Problem => new Problem([], [Applicant::person('a', []), Applicant::person('b', [])], limits: $limits), 'A problem has at most 1 applicants.'];
        yield 'too many choices' => [static fn (): Problem => new Problem([new Slot('a', 1), new Slot('b', 1)], [Applicant::person('p', ['a', 'b'])], limits: new Limits(choices: 1)), 'Applicant "p" ranks more than 1 slots.'];
        yield 'too many members' => [static fn (): Problem => new Problem([], [Applicant::group('g', ['a', 'b'], [])], limits: $limits), 'Applicant "g" has more than 1 members.'];
        yield 'slot twice' => [static fn (): Problem => new Problem([new Slot('a', 1), new Slot('a', 2)], []), 'Slot "a" is given twice.'];
        yield 'applicant twice' => [static fn (): Problem => new Problem([], [Applicant::person('a', []), Applicant::person('a', [])]), 'Applicant "a" is given twice.'];
        yield 'unknown choice' => [static fn (): Problem => new Problem([], [Applicant::person('a', ['t'])]), 'The problem has no slot "t".'];
        yield 'member in two applicants' => [static fn (): Problem => new Problem([], [Applicant::person('a', []), Applicant::group('g', ['b', 'a'], [])]), 'Member "a" belongs to two applicants.'];
        yield 'forbidden unknown applicant' => [static fn (): Problem => new Problem([new Slot('t', 1)], [], [new Pair('a', 't')]), 'The problem has no applicant "a".'];
        yield 'forbidden unknown slot' => [static fn (): Problem => new Problem([], [Applicant::person('a', [])], [new Pair('a', 't')]), 'The problem has no slot "t".'];
        yield 'forced unknown applicant' => [static fn (): Problem => new Problem([new Slot('t', 1)], [], [], [new Pair('a', 't')]), 'The problem has no applicant "a".'];
        yield 'forced unknown slot' => [static fn (): Problem => new Problem([], [Applicant::person('a', [])], [], [new Pair('a', 't')]), 'The problem has no slot "t".'];
        yield 'forced twice' => [static fn (): Problem => new Problem([new Slot('t', 2), new Slot('u', 2)], [Applicant::person('a', [])], [], [new Pair('a', 't'), new Pair('a', 'u')]), 'Applicant "a" is forced into two slots.'];
        yield 'forced and forbidden' => [static fn (): Problem => new Problem([new Slot('t', 2)], [Applicant::person('a', [])], [new Pair('a', 't')], [new Pair('a', 't')]), 'Applicant "a" is both forced into and forbidden from slot "t".'];
        yield 'forced over capacity' => [static fn (): Problem => new Problem([new Slot('t', 2)], [Applicant::person('a', []), Applicant::group('g', ['b', 'c'], [])], [], [new Pair('a', 't'), new Pair('g', 't')]), 'The applicants forced into slot "t" exceed its capacity.'];
        yield 'forced group over capacity' => [static fn (): Problem => new Problem([new Slot('t', 1)], [Applicant::group('g', ['b', 'c'], [])], [], [new Pair('g', 't')]), 'The applicants forced into slot "t" exceed its capacity.'];
    }

    /**
     * @param Closure(): Problem $build
     */
    #[DataProvider('invalidProblems')]
    public function testInvalidProblemsAreRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidProblem::class);
        $this->expectExceptionMessageIs($message);

        $build();
    }

    public function testForbiddenAndForcedPairsOfSeveralApplicantsAreAllKept(): void
    {
        $problem = new Problem(
            [new Slot('t1', 3), new Slot('t2', 3)],
            [Applicant::person('ana', []), Applicant::person('bo', []), Applicant::person('cy', [])],
            [new Pair('ana', 't1'), new Pair('bo', 't2'), new Pair('cy', 't1')],
            [new Pair('ana', 't2'), new Pair('bo', 't1')],
        );

        self::assertTrue($problem->isForbidden('ana', 't1'));
        self::assertTrue($problem->isForbidden('bo', 't2'));
        self::assertTrue($problem->isForbidden('cy', 't1'));
        self::assertFalse($problem->isForbidden('cy', 't2'));
        self::assertSame('t2', $problem->forcedSlot('ana'));
        self::assertSame('t1', $problem->forcedSlot('bo'));
        self::assertNull($problem->forcedSlot('cy'));
    }
}
