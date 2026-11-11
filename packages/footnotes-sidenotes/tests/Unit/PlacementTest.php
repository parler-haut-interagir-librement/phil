<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\ProblemKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Placement::class)]
#[CoversClass(ProblemKind::class)]
final class PlacementTest extends TestCase
{
    public function testTheNotesInFlowAreThoseOfTheMarginAndThePageFoot(): void
    {
        self::assertTrue(Placement::Margin->isInFlow());
        self::assertTrue(Placement::PageFoot->isInFlow());
        self::assertFalse(Placement::EndOfDocument->isInFlow());
        self::assertFalse(Placement::EndOfSection->isInFlow());
    }

    public function testOnlyARepeatedReferenceIsNoError(): void
    {
        self::assertFalse(ProblemKind::RepeatedReference->isError());
        self::assertTrue(ProblemKind::MissingNote->isError());
        self::assertTrue(ProblemKind::UnreferencedNote->isError());
        self::assertTrue(ProblemKind::DuplicateId->isError());
        self::assertTrue(ProblemKind::ReferenceInNote->isError());
    }
}
