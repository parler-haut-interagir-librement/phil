<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Meta;

use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Tests\Fixture\Attribute\Payload;
use Phil\Enums\Tests\Fixture\Attribute\Shade;
use Phil\Enums\Tests\Fixture\EnumerableEnum;
use Phil\Enums\Tests\Fixture\LabelledEnum;
use Phil\Enums\Tests\Fixture\MetaEnum;
use Phil\Enums\Tests\Fixture\ShadedEnum;
use Phil\Enums\Tests\Fixture\SingleCaseEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;
use ValueError;

/**
 * What the trait tests leave out: exact error messages, custom accessor names, meta properties declared by a
 * trait, a one-case enum, and the default labels of `stringOptions()` for every shape of case name.
 *
 * @internal
 */
#[CoversClass(Meta::class)]
final class CoverageTest extends TestCase
{
    #[Test]
    public function metaKeepsItsPropertiesAsAList(): void
    {
        self::assertSame([Label::class], new Meta(...['label' => Label::class])->metaProperties);
    }

    #[Test]
    public function aCustomMethodNameNamesTheAccessor(): void
    {
        self::assertSame('tint', Shade::method());
        self::assertSame('label', Label::method());
    }

    #[Test]
    public function aTraitMayDeclareMetaPropertiesForItsEnums(): void
    {
        self::assertSame('Dark', ShadedEnum::DARK->label());
        self::assertSame('black', ShadedEnum::DARK->tint());
    }

    #[Test]
    public function aOneCaseBackedEnumStillMapsNamesToValues(): void
    {
        self::assertSame(['ONLY' => 'only'], SingleCaseEnum::options());
    }

    #[Test]
    public function fromNameSaysWhichNameAndWhichEnum(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('"NOPE" is not a valid name for enum '.EnumerableEnum::class);

        EnumerableEnum::fromName('NOPE');
    }

    #[Test]
    public function fromMetaWritesAScalarValueAsIs(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('Enum '.MetaEnum::class.' does not have a case with a meta property "'.Payload::class.'" of value "3"');

        MetaEnum::fromMeta(new Payload(3));
    }

    #[Test]
    public function fromMetaWritesAStringableValueAsItsString(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('of value "heavy"');

        MetaEnum::fromMeta(new Payload(new class implements Stringable {
            public function __toString(): string
            {
                return 'heavy';
            }
        }));
    }

    #[Test]
    public function fromMetaWritesAnyOtherValueAsItsType(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('of value "array"');

        MetaEnum::fromMeta(new Payload(['heavy']));
    }

    #[Test]
    public function theDefaultLabelReadsEveryShapeOfCaseName(): void
    {
        self::assertSame(
            '<option value="snake">Snake case</option>|'
            .'<option value="upper">Upper</option>|'
            .'<option value="pascal">Pascal case</option>|'
            .'<option value="mixed">Aé b</option>|'
            .'<option value="summer">été draft</option>',
            LabelledEnum::stringOptions(glue: '|'),
        );
    }
}
