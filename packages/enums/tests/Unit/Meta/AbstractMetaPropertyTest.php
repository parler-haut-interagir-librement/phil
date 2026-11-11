<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Meta;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Tests\Fixture\Attribute\Color;
use Phil\Enums\Tests\Fixture\Attribute\Shade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractMetaProperty::class)]
final class AbstractMetaPropertyTest extends TestCase
{
    #[Test]
    public function constructorStoresValue(): void
    {
        $desc = new Description('hello');
        self::assertSame('hello', $desc->value);
    }

    #[Test]
    public function transformIsApplied(): void
    {
        $color = new Color('blue');
        self::assertSame('tone-blue', $color->value);
    }

    #[Test]
    public function defaultValueIsNull(): void
    {
        self::assertNull(Description::defaultValue());
    }

    #[Test]
    public function makeCreatesNewInstance(): void
    {
        $description = Description::make('second');

        self::assertSame('second', $description->value);
        self::assertInstanceOf(Description::class, $description);
    }

    #[Test]
    public function methodReturnsDerivedNameFromClassName(): void
    {
        self::assertSame('description', Description::method());
        self::assertSame('label', Label::method());
        self::assertSame('group', Group::method());
    }

    #[Test]
    public function methodReturnsCustomNameWhenOverridden(): void
    {
        // Color does not override customMethodName(): its accessor is its short class name, first letter lowered.
        self::assertSame('color', Color::method());
    }

    /**
     * `make()` is static, so a value is looked up as `Label::make('Draft')`, and transformed as the constructor does.
     */
    #[Test]
    public function makeIsCalledStaticallyAndTransforms(): void
    {
        self::assertSame('Draft', Label::make('Draft')->value);
        self::assertSame('tone-teal', Color::make('teal')->value);
    }

    #[Test]
    public function aCustomMethodNameNamesTheAccessor(): void
    {
        self::assertSame('tint', Shade::method());
    }
}
