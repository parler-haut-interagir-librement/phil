<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Phil\Assignment\Applicant;
use Phil\Assignment\Assignment;
use Phil\Assignment\ChoiceOutcome;
use Phil\Assignment\PlacedBy;
use Phil\Assignment\Placement;
use Phil\Assignment\Problem;
use Phil\Assignment\RefusedChoice;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;
use Phil\Assignment\Tests\Unit\Fixture\Outcomes;
use Phil\Assignment\Unplaced;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Assignment::class)]
final class AssignmentTest extends TestCase
{
    public function testTheResultAnswersPerApplicantPerMemberAndPerSlot(): void
    {
        $assignment = $this->assignment();

        self::assertSame(['ana', 'g', 'gm'], Outcomes::placed($assignment));
        self::assertSame(['al' => [], 'bo' => ['c' => 'closed', 'a' => 'full']], Outcomes::unplaced($assignment));
        self::assertTrue($assignment->isUnplaced('bo'));
        self::assertFalse($assignment->isUnplaced('ana'));
        self::assertSame('a', $assignment->placementOf('ana')?->slot);
        self::assertNull($assignment->placementOf('bo'));
        self::assertSame('b', $assignment->placementOfMember('dee')?->slot);
        self::assertSame('b', $assignment->placementOfMember('cy')?->slot);
        self::assertNull($assignment->placementOfMember('bo'));
        self::assertNull($assignment->placementOfMember('nobody'));
        self::assertSame(['ana', 'gm'], $assignment->membersOf('a'));
        self::assertSame(['cy', 'dee'], $assignment->membersOf('b'));
        self::assertSame([], $assignment->membersOf('c'));
        self::assertSame(2, $assignment->occupancy('b'));
        self::assertSame([1 => 1, 2 => 1], $assignment->rankCounts());
    }

    public function testTheResultBecomesPlainDataForAnAuditLog(): void
    {
        self::assertSame([
            'method' => 'rounds/same',
            'seed' => 'audit',
            'draw_order' => ['g', 'bo', 'ana', 'gm'],
            'placements' => [
                ['applicant' => 'ana', 'slot' => 'a', 'placed_by' => 'round', 'rank' => 1, 'round' => 1],
                ['applicant' => 'g', 'slot' => 'b', 'placed_by' => 'round', 'rank' => 2, 'round' => 2],
                ['applicant' => 'gm', 'slot' => 'a', 'placed_by' => 'forced', 'rank' => null, 'round' => null],
            ],
            'unplaced' => [
                ['applicant' => 'al', 'choices' => []],
                ['applicant' => 'bo', 'choices' => [['slot' => 'c', 'outcome' => 'closed'], ['slot' => 'a', 'outcome' => 'full']]],
            ],
            'closed_slots' => ['c'],
            'cost' => 1,
        ], $this->assignment()->toArray());
    }

    private function assignment(): Assignment
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 2), new Slot('c', 2, 2)],
            [Applicant::person('gm', []), Applicant::person('al', []), Applicant::person('ana', ['a']), Applicant::person('bo', ['c', 'a']), Applicant::group('g', ['cy', 'dee'], ['a', 'b'])],
        );

        return new Assignment(
            $problem,
            'rounds/same',
            Seed::fromString('audit'),
            ['g', 'bo', 'ana', 'gm'],
            [
                new Placement('gm', 'a', PlacedBy::Forced, null),
                new Placement('g', 'b', PlacedBy::Round, 2, 2),
                new Placement('ana', 'a', PlacedBy::Round, 1, 1),
            ],
            [new Unplaced('bo', [new RefusedChoice('c', ChoiceOutcome::Closed), new RefusedChoice('a', ChoiceOutcome::Full)]), new Unplaced('al', [])],
            ['c'],
            1,
        );
    }

    public function testRankCountsAddUpAndComeInRankOrder(): void
    {
        $problem = new Problem([new Slot('a', 5)], [Applicant::person('p', ['a']), Applicant::person('q', ['a']), Applicant::person('r', ['a']), Applicant::person('s', ['a']), Applicant::person('t', ['a'])]);
        $assignment = new Assignment($problem, 'test', Seed::fromString('x'), ['p', 'q', 'r', 's', 't'], [
            new Placement('p', 'a', PlacedBy::Round, 3),
            new Placement('q', 'a', PlacedBy::Round, 1),
            new Placement('r', 'a', PlacedBy::Round, 3),
            new Placement('s', 'a', PlacedBy::Forced, null),
            new Placement('t', 'a', PlacedBy::Round, 3),
        ], [], [], 0);

        self::assertSame([1 => 1, 3 => 3], $assignment->rankCounts());
    }
}
