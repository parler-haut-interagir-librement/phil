<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Tests\Fixture\StringBackedEnum;
use Phil\Enums\Traits\AsSelfAwareableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsSelfAwareableEnumTrait::class)]
final class AsSelfAwareableEnumTraitTest extends TestCase
{
    #[Test]
    public function pureEnumIsPure(): void
    {
        self::assertTrue(PureEnum::isPure());
        self::assertFalse(PureEnum::isBacked());
    }

    #[Test]
    public function intBackedEnumIsBacked(): void
    {
        self::assertTrue(IntBackedEnum::isBacked());
        self::assertFalse(IntBackedEnum::isPure());
    }

    #[Test]
    public function intBackedEnumIsBackedByInteger(): void
    {
        self::assertTrue(IntBackedEnum::isBackedByInteger());
        self::assertFalse(IntBackedEnum::isBackedByString());
    }

    #[Test]
    public function stringBackedEnumIsBackedByString(): void
    {
        self::assertTrue(StringBackedEnum::isBackedByString());
        self::assertFalse(StringBackedEnum::isBackedByInteger());
    }
}
