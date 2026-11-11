<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Meta;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\RequiredMeta;
use Phil\Enums\Meta\Reflection;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Tests\Fixture\Incomplete;
use Phil\Enums\Tests\Fixture\Level;
use Phil\Enums\Tests\Fixture\Planet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RequiredMeta::class)]
final class RequiredMetaTest extends TestCase
{
    #[Test]
    public function itListsWhatEachCaseMissesOfEveryRequiredMetaAttribute(): void
    {
        self::assertSame([
            'Half' => [Description::class],
            'Bare' => [Label::class, Description::class, Mass::class],
        ], Reflection::missingRequiredMeta(Incomplete::class));
    }

    #[Test]
    public function anEnumThatRequiresNothingMissesNothing(): void
    {
        self::assertSame([], Reflection::missingRequiredMeta(Planet::class));
        self::assertSame([], Reflection::missingRequiredMeta(Level::class));
    }

    #[Test]
    public function theAttributeKeepsAListEvenFromNamedArguments(): void
    {
        self::assertSame([Label::class, Description::class], new RequiredMeta(label: Label::class, description: Description::class)->metaProperties);
    }
}
