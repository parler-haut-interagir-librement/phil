<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use LogicException;
use Phil\Enums\Tests\Fixture\Level;
use Phil\Enums\Tests\Fixture\Permission;
use Phil\Enums\Tests\Fixture\TwoDefaults;
use Phil\Enums\Traits\AsDefaultableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsDefaultableEnumTrait::class)]
final class AsDefaultableEnumTraitTest extends TestCase
{
    #[Test]
    public function itReturnsTheMarkedCase(): void
    {
        self::assertSame(Level::Medium, Level::default());
        self::assertSame(Level::Medium, Level::tryDefault());
    }

    #[Test]
    public function anEnumThatMarksNoCaseHasNoDefault(): void
    {
        self::assertNull(Permission::tryDefault());
        self::assertNull(Permission::tryDefault());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Enum '.Permission::class.' marks no case with #[DefaultCase].');

        Permission::default();
    }

    #[Test]
    public function anEnumThatMarksTwoCasesIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Enum '.TwoDefaults::class.' marks First, Third with #[DefaultCase]; it may mark one case only.');

        TwoDefaults::tryDefault();
    }
}
