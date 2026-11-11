<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Meta;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Meta\Reflection;
use Phil\Enums\Tests\Fixture\Attribute\Color;
use Phil\Enums\Tests\Fixture\Attribute\Counted;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Tests\Fixture\CaptionedEnum;
use Phil\Enums\Tests\Fixture\CountedEnum;
use Phil\Enums\Tests\Fixture\MetaEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The metadata reader reads each case once, and keeps the first value of a repeated meta property.
 *
 * @internal
 */
#[CoversClass(Reflection::class)]
final class ReflectionCacheTest extends TestCase
{
    /**
     * Whatever the tests before it read, a case read once is read from memory afterwards: a second accessor call
     * instantiates nothing, and `fromMeta()` only the property it is given.
     */
    #[Test]
    public function eachCaseIsReadOnce(): void
    {
        self::assertSame('first', CountedEnum::FIRST->counted());
        $read = Counted::$instances;

        self::assertSame('first', CountedEnum::FIRST->counted());
        self::assertSame($read, Counted::$instances);

        self::assertSame(CountedEnum::FIRST, CountedEnum::fromMeta(new Counted('first')));
        self::assertSame($read + 1, Counted::$instances);
    }

    #[Test]
    public function theFirstOfARepeatedMetaPropertyWins(): void
    {
        self::assertSame('second', CountedEnum::SECOND->counted());
    }

    #[Test]
    public function everyValueOfARepeatedMetaPropertyIsKeptInOrder(): void
    {
        self::assertSame(['second', 'also'], Reflection::metaValueList(Counted::class, CountedEnum::SECOND));
        self::assertSame(['first'], Reflection::metaValueList(Counted::class, CountedEnum::FIRST));
        self::assertSame([], Reflection::metaValueList(Mass::class, CountedEnum::FIRST));
    }

    /**
     * The meta property an accessor reads, among those the enum and its traits enable; `null` for any other name.
     */
    #[Test]
    public function anAccessorNamesTheMetaPropertyItReads(): void
    {
        self::assertSame(Label::class, Reflection::metaPropertyNamed(MetaEnum::BOILING, 'label'));
        self::assertSame(Color::class, Reflection::metaPropertyNamed(MetaEnum::BOILING, 'color'));
        self::assertNull(Reflection::metaPropertyNamed(MetaEnum::BOILING, 'lable'));
        self::assertNull(Reflection::metaPropertyNamed(MetaEnum::BOILING, 'Label'));
        self::assertSame([Description::class, Label::class, Color::class], Reflection::metaProperties(MetaEnum::BOILING));
    }

    #[Test]
    public function ofTwoPropertiesWithOneAccessorTheFirstListedAnswers(): void
    {
        self::assertSame(Label::class, Reflection::metaPropertyNamed(CaptionedEnum::ONLY, 'label'));
        self::assertSame([Label::class], Reflection::metaProperties(CaptionedEnum::ONLY));
        self::assertSame('From the label', CaptionedEnum::ONLY->label());
    }
}
