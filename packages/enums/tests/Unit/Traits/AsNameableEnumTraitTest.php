<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Traits\AsNameableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsNameableEnumTrait::class)]
final class AsNameableEnumTraitTest extends TestCase
{
    #[Test]
    public function namesReturnsCaseNames(): void
    {
        self::assertSame(['ONE', 'TWO', 'THREE'], PureEnum::names());
        self::assertSame(['ONE', 'TWO', 'THREE'], IntBackedEnum::names());
    }
}
