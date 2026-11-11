<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\EnumerableEnum;
use Phil\Enums\Traits\AsEnumerableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The aggregate trait brings each trait it is made of.
 *
 * @internal
 */
#[CoversTrait(AsEnumerableEnumTrait::class)]
final class AsEnumerableEnumTraitTest extends TestCase
{
    #[Test]
    public function itBringsEveryTrait(): void
    {
        self::assertSame(['OPEN', 'CLOSED'], EnumerableEnum::names());
        self::assertSame(['open', 'closed'], EnumerableEnum::values());
        self::assertSame(['OPEN' => 'open', 'CLOSED' => 'closed'], EnumerableEnum::options());
        self::assertSame('open', EnumerableEnum::OPEN());
        self::assertSame('closed', (EnumerableEnum::CLOSED)());
        self::assertTrue(EnumerableEnum::OPEN->is(EnumerableEnum::OPEN));
        self::assertSame(EnumerableEnum::CLOSED, EnumerableEnum::fromName('CLOSED'));
        self::assertSame('Closed', EnumerableEnum::CLOSED->label());
        self::assertTrue(EnumerableEnum::isBacked());
    }
}
