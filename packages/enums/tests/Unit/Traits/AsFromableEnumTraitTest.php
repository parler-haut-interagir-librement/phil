<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\IntBackedEnum;
use Phil\Enums\Tests\Fixture\PureEnum;
use Phil\Enums\Traits\AsFromambleEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @internal
 */
#[CoversTrait(AsFromambleEnumTrait::class)]
final class AsFromableEnumTraitTest extends TestCase
{
    #[Test]
    public function fromNameReturnsMatchingCase(): void
    {
        self::assertSame(PureEnum::ONE, PureEnum::fromName('ONE'));
        self::assertSame(IntBackedEnum::TWO, IntBackedEnum::fromName('TWO'));
    }

    #[Test]
    public function fromNameThrowsOnUnknownName(): void
    {
        $this->expectException(ValueError::class);
        PureEnum::fromName('NOPE');
    }

    #[Test]
    public function tryFromNameReturnsNullOnMiss(): void
    {
        self::assertNull(PureEnum::tryFromName('NOPE'));
        self::assertNull(IntBackedEnum::tryFromName('NOPE'));
    }

    #[Test]
    public function fromAndTryFromWorkOnPureEnums(): void
    {
        self::assertSame(PureEnum::TWO, PureEnum::from('TWO'));
        self::assertNull(PureEnum::tryFrom('NOPE'));
    }

    #[Test]
    public function fromThrowsOnUnknownPureEnumName(): void
    {
        $this->expectException(ValueError::class);
        PureEnum::from('NOPE');
    }
}
