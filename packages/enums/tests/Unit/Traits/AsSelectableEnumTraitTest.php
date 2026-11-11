<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Traits\AsSelectableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsSelectableEnumTrait::class)]
final class AsSelectableEnumTraitTest extends TestCase
{
    #[Test]
    public function optionsReturnsAssociativeArrayForBackedEnum(): void
    {
        self::assertSame(
            ['ONE' => 1, 'TWO' => 2, 'THREE' => 3],
            IntBackedEnum::options(),
        );
    }

    #[Test]
    public function optionsReturnsListOfNamesForPureEnum(): void
    {
        self::assertSame(['ONE', 'TWO', 'THREE'], PureEnum::options());
    }
}
