<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Meta\Reflection;
use Phil\Enums\Tests\Fixture\Level;
use Phil\Enums\Tests\Fixture\Permission;
use Phil\Enums\Traits\AsParsableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @internal
 */
#[CoversTrait(AsParsableEnumTrait::class)]
final class AsParsableEnumTraitTest extends TestCase
{
    #[Test]
    public function itReadsAValueANameOrAnAlias(): void
    {
        self::assertSame(Level::Medium, Level::parse('medium'));
        self::assertSame(Level::Medium, Level::parse('Medium'));
        self::assertSame(Level::Low, Level::parse('minor'));
        self::assertSame(Level::High, Level::parse('major'));
        self::assertSame(Level::High, Level::parse(3));
        self::assertSame(Permission::Write, Permission::parse(2));
        self::assertSame(Permission::Write, Permission::parse('2'));
        self::assertSame(Permission::Write, Permission::parse('Write'));
    }

    #[Test]
    public function aValueWinsOverAnotherCasesAlias(): void
    {
        // `Medium` carries the alias "high", which is the value of `High`.
        self::assertSame(Level::High, Level::parse('high'));
    }

    #[Test]
    public function aNameWinsOverAnotherCasesAlias(): void
    {
        self::assertSame(Level::High, Level::tryParse('High'));
    }

    #[Test]
    public function caseIsIgnoredOnlyWhenAsked(): void
    {
        self::assertNull(Level::tryParse('MAJOR'));
        self::assertNull(Level::tryParse('LOW'));
        self::assertSame(Level::High, Level::tryParse('MAJOR', ignoreCase: true));
        self::assertSame(Level::Low, Level::tryParse('LOW', ignoreCase: true));
        self::assertSame(Level::Medium, Level::tryParse('mEdIuM', ignoreCase: true));
        self::assertSame(Permission::Write, Permission::tryParse('2', ignoreCase: true));
        self::assertSame(Permission::Write, Permission::tryParse('wRITE', ignoreCase: true));
    }

    #[Test]
    public function theReaderRespectsCaseByDefaultForAnyEnum(): void
    {
        self::assertNull(Reflection::parse(Level::class, 'LOW'));
        self::assertSame(Level::Low, Reflection::parse(Level::class, 'LOW', true));
    }

    #[Test]
    public function parseRespectsCaseByDefault(): void
    {
        $this->expectException(ValueError::class);

        Level::parse('LOW');
    }

    #[Test]
    public function anUnknownInputIsRefused(): void
    {
        self::assertNull(Level::tryParse('extreme'));
        self::assertNull(Level::tryParse(''));

        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('"extreme" is neither a value, a name nor an alias of enum '.Level::class.'.');

        Level::parse('extreme');
    }
}
