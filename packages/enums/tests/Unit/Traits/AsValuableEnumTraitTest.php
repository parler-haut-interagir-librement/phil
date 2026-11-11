<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Traits\AsValuableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsValuableEnumTrait::class)]
final class AsValuableEnumTraitTest extends TestCase
{
    #[Test]
    public function valuesReturnsBackedValues(): void
    {
        self::assertSame([1, 2, 3], IntBackedEnum::values());
    }

    #[Test]
    public function valuesReturnsNamesForPureEnum(): void
    {
        self::assertSame(['ONE', 'TWO', 'THREE'], PureEnum::values());
    }
}
