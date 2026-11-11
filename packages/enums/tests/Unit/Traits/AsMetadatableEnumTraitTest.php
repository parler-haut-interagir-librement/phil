<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use BadMethodCallException;
use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Tests\Fixture\MetaEnum;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @internal
 */
#[CoversTrait(AsMetadatableEnumTrait::class)]
final class AsMetadatableEnumTraitTest extends TestCase
{
    #[Test]
    public function metaPropertyIsAccessibleViaMagicCall(): void
    {
        self::assertSame('The water is cold', MetaEnum::COLD->description());
        self::assertSame('Boiling', MetaEnum::BOILING->label());
    }

    #[Test]
    public function colorMetaPropertyAppliesTransform(): void
    {
        self::assertSame('tone-blue', MetaEnum::COLD->color());
        self::assertSame('tone-orange', MetaEnum::BOILING->color());
    }

    #[Test]
    public function caseWithoutAttributeReturnsDefaultValue(): void
    {
        // Description and Label default to null, Color default to null
        self::assertNull(MetaEnum::UNPLUGGED->description());
        self::assertNull(MetaEnum::UNPLUGGED->label());
        self::assertNull(MetaEnum::UNPLUGGED->color());
    }

    #[Test]
    public function tryFromMetaReturnsMatchingCase(): void
    {
        $result = MetaEnum::tryFromMeta(new Description('The water boils'));
        self::assertSame(MetaEnum::BOILING, $result);
    }

    #[Test]
    public function tryFromMetaReturnsNullOnNoMatch(): void
    {
        self::assertNull(MetaEnum::tryFromMeta(new Description('Unknown')));
    }

    #[Test]
    public function fromMetaReturnsMatchingCase(): void
    {
        self::assertSame(MetaEnum::COLD, MetaEnum::fromMeta(new Label('Cold')));
    }

    #[Test]
    public function fromMetaThrowsOnNoMatch(): void
    {
        $this->expectException(ValueError::class);
        MetaEnum::fromMeta(new Label('Unknown'));
    }

    /**
     * A misspelled accessor fails as an undefined method does, never as a missing value.
     */
    #[Test]
    public function undefinedMetaPropertyFailsAsAnUndefinedMethod(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageIsOrContains('Call to undefined method '.MetaEnum::class.'::lable()');

        MetaEnum::COLD->__call('lable', []);
    }

    #[Test]
    public function fromMetaNamesTheValueItLookedFor(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('Enum '.MetaEnum::class.' does not have a case with a meta property "'.Label::class.'" of value "Unknown"');

        MetaEnum::fromMeta(Label::make('Unknown'));
    }
}
