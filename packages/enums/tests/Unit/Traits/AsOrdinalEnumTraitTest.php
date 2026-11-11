<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\Permission;
use Phil\Enums\Traits\AsOrdinalEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsOrdinalEnumTrait::class)]
final class AsOrdinalEnumTraitTest extends TestCase
{
    #[Test]
    public function theOrdinalIsTheDeclarationPosition(): void
    {
        self::assertSame(0, Permission::Read->ordinal());
        self::assertSame(1, Permission::Write->ordinal());
        self::assertSame(2, Permission::Delete->ordinal());
    }

    #[Test]
    public function nextAndPreviousStopAtTheEndsUnlessTheyCycle(): void
    {
        self::assertSame(Permission::Write, Permission::Read->next());
        self::assertSame(Permission::Delete, Permission::Write->next());
        self::assertNull(Permission::Delete->next());
        self::assertSame(Permission::Read, Permission::Delete->next(cycle: true));
        self::assertSame(Permission::Delete, Permission::Write->next(cycle: true));

        self::assertSame(Permission::Write, Permission::Delete->previous());
        self::assertSame(Permission::Read, Permission::Write->previous());
        self::assertNull(Permission::Read->previous());
        self::assertSame(Permission::Delete, Permission::Read->previous(cycle: true));
        self::assertSame(Permission::Read, Permission::Write->previous(cycle: true));
    }

    #[Test]
    public function casesCompareByPosition(): void
    {
        self::assertSame(-1, Permission::Read->compareTo(Permission::Delete));
        self::assertSame(0, Permission::Write->compareTo(Permission::Write));
        self::assertSame(1, Permission::Delete->compareTo(Permission::Read));

        self::assertTrue(Permission::Read->isBefore(Permission::Write));
        self::assertFalse(Permission::Write->isBefore(Permission::Write));
        self::assertFalse(Permission::Delete->isBefore(Permission::Write));
        self::assertTrue(Permission::Delete->isAfter(Permission::Write));
        self::assertFalse(Permission::Write->isAfter(Permission::Write));
        self::assertFalse(Permission::Read->isAfter(Permission::Write));

        self::assertTrue(Permission::Write->isBetween(Permission::Read, Permission::Delete));
        self::assertTrue(Permission::Read->isBetween(Permission::Read, Permission::Write));
        self::assertTrue(Permission::Write->isBetween(Permission::Read, Permission::Write));
        self::assertFalse(Permission::Delete->isBetween(Permission::Read, Permission::Write));
        self::assertFalse(Permission::Read->isBetween(Permission::Write, Permission::Delete));
    }
}
