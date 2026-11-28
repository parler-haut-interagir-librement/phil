<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use Phil\RelatedContent\Pairs\Signal;
use Phil\RelatedContent\Reason;
use Phil\RelatedContent\Relation\RelationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reason::class)]
#[CoversClass(RelationType::class)]
#[CoversClass(Signal::class)]
final class ReasonTest extends TestCase
{
    public function testManualReasonsAreThoseAPersonChose(): void
    {
        $manual = array_values(array_filter(Reason::cases(), static fn (Reason $reason): bool => $reason->isManual()));

        self::assertSame([Reason::Pinned, Reason::Accessory, Reason::Complement, Reason::Upgrade], $manual);
    }

    public function testEachRelationTypeHasItsReason(): void
    {
        self::assertSame(Reason::Accessory, RelationType::Accessory->reason());
        self::assertSame(Reason::Complement, RelationType::Complement->reason());
        self::assertSame(Reason::Upgrade, RelationType::Upgrade->reason());
    }

    public function testEachSignalHasItsReasonAndDirection(): void
    {
        self::assertSame(Reason::BoughtTogether, Signal::BoughtTogether->reason());
        self::assertSame(Reason::ViewedNext, Signal::ViewedNext->reason());
        self::assertTrue(Signal::BoughtTogether->isSymmetric());
        self::assertFalse(Signal::ViewedNext->isSymmetric());
    }
}
