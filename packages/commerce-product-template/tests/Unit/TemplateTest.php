<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Combination;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Exclusion;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\Tests\Unit\Fixture\Shirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Template::class)]
#[CoversClass(Combination::class)]
#[CoversClass(Exclusion::class)]
final class TemplateTest extends TestCase
{
    public function testATemplateKeepsItsPartsInOrder(): void
    {
        $template = Shirts::template();

        self::assertSame('unisex', $template->code);
        self::assertSame('T-shirt unisexe', $template->name->in('fr'));
        self::assertSame('Supplier 64000', $template->supplier);
        self::assertSame(['version', 'size', 'colour'], array_map(static fn (Axis $axis): string => $axis->code, $template->axes()));
        self::assertSame('size', $template->axis('size')?->code);
        self::assertNull($template->axis('fit'));
        self::assertSame(['front', 'back'], array_map(static fn (Face $face): string => $face->code, $template->faces()));
        self::assertSame('back', $template->face('back')?->code);
        self::assertNull($template->face('sleeve'));
        self::assertCount(1, $template->exclusions);
        self::assertSame('{design}{lang}{version}{size}{colour}', $template->skuPattern->pattern);
        self::assertSame(2_500, $template->pricing->base->minorUnits);
    }

    public function testItsCombinationsVaryTheLastAxisFastestAndSkipTheExcluded(): void
    {
        $combinations = array_map(static fn (Combination $combination): string => $combination->describe(), iterator_to_array(Shirts::template()->combinations(), false));

        self::assertSame([
            'version O, size M, colour BK',
            'version O, size M, colour PK',
            'version O, size 2X, colour BK',
            'version L, size M, colour BK',
            'version L, size M, colour PK',
            'version L, size 2X, colour BK',
            'version C, size M, colour BK',
            'version C, size M, colour PK',
            'version C, size 2X, colour BK',
        ], $combinations);
    }

    public function testATemplateOfOneAxisOfOneValueHasOneCombination(): void
    {
        $template = Template::of('mug', Label::of(['en' => 'Mug']), SkuPattern::parse('{design}'), Pricing::of(Shirts::cad(1_500)), [
            Axis::of('size', Label::of(['en' => 'Size']), AxisValue::of('U', Label::of(['en' => 'One size']))),
        ]);

        self::assertSame(['size U'], array_map(static fn (Combination $combination): string => $combination->describe(), iterator_to_array($template->combinations(), false)));
        self::assertSame([], $template->faces());
        self::assertSame([], $template->exclusions);
        self::assertNull($template->supplier);
    }

    public function testATemplateMakesWhatNoExclusionExcludes(): void
    {
        $template = Shirts::template();
        $pink = $template->axis('colour')?->value('PK');
        $black = $template->axis('colour')?->value('BK');
        $big = $template->axis('size')?->value('2X');
        $regular = $template->axis('version')?->value('O');
        self::assertNotNull($pink);
        self::assertNotNull($black);
        self::assertNotNull($big);
        self::assertNotNull($regular);

        self::assertFalse($template->makes(new Combination(['version' => $regular, 'size' => $big, 'colour' => $pink])));
        self::assertTrue($template->makes(new Combination(['version' => $regular, 'size' => $big, 'colour' => $black])));
    }

    public function testACombinationMatchesAConditionOnEveryAxisItNames(): void
    {
        $combination = new Combination(['size' => AxisValue::of('M', Label::of(['en' => 'M'])), 'colour' => AxisValue::of('BK', Label::of(['en' => 'Black']))]);

        self::assertTrue($combination->matches([]));
        self::assertTrue($combination->matches(['size' => ['S', 'M']]));
        self::assertTrue($combination->matches(['size' => ['M'], 'colour' => ['BK']]));
        self::assertFalse($combination->matches(['size' => ['M'], 'colour' => ['WH']]));
        self::assertFalse($combination->matches(['fit' => ['M']]));
        self::assertSame(['size' => 'M', 'colour' => 'BK'], $combination->codes());
        self::assertSame('BK', $combination->value('colour')?->code);
        self::assertNull($combination->value('fit'));
    }

    public function testAConditionIsCheckedAndKeptOnceEach(): void
    {
        self::assertSame(['size' => ['M', 'L'], 'colour' => ['0']], Combination::condition(['size' => ['M', 'M', 'L'], 'colour' => ['0']], 'X'));
        self::assertSame(['a'.str_repeat('b', 31) => ['A._-'.str_repeat('9', 12)]], Combination::condition(['a'.str_repeat('b', 31) => ['A._-'.str_repeat('9', 12)]], 'X'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedConditions(): iterable
    {
        $axes = 'An exclusion names, by axis code, a list of one value code or more.';
        $codes = 'An exclusion names value codes of 1 to 16 letters, digits, ".", "_" or "-".';

        yield 'a digit axis' => [[0 => ['M']], $axes];
        yield 'an uppercase axis' => [['Size' => ['M']], $axes];
        yield 'a long axis' => [['a'.str_repeat('b', 32) => ['M']], $axes];
        yield 'an axis with a line after' => [["size\n" => ['M']], $axes];
        yield 'not a list' => [['size' => 'M'], $axes];
        yield 'a map' => [['size' => ['a' => 'M']], $axes];
        yield 'empty' => [['size' => []], $axes];
        yield 'not a string' => [['size' => [1]], $codes];
        yield 'a long code' => [['size' => [str_repeat('A', 17)]], $codes];
        yield 'a code starting with a dot' => [['size' => ['.M']], $codes];
        yield 'a code with a line after' => [['size' => ["M\n"]], $codes];
    }

    /**
     * @param array<array-key, mixed> $condition
     */
    #[DataProvider('refusedConditions')]
    public function testAConditionRefusesWhatIsNotOne(array $condition, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        Exclusion::of($condition); // @phpstan-ignore argument.type (what storage might hold)
    }

    public function testAnExclusionNamesOneAxisAtLeast(): void
    {
        self::assertSame(['size' => ['4X']], Exclusion::of(['size' => ['4X']])->values);

        $this->expectExceptionObject(InvalidDefinition::because('An exclusion names one axis at least.'));

        Exclusion::of([]);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedTemplates(): iterable
    {
        $name = Label::of(['en' => 'Shirt']);
        $pattern = SkuPattern::parse('{design}{size}');
        $pricing = Pricing::of(Shirts::cad(2_500));
        $size = Axis::of('size', Label::of(['en' => 'Size']), AxisValue::of('M', Label::of(['en' => 'M'])), AxisValue::of('L', Label::of(['en' => 'L'])));
        $front = Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 0, 0, 1, 1));
        $axis = static fn (string $code, int $count): Axis => Axis::of($code, Label::of(['en' => $code]), ...array_map(static fn (int $index): AxisValue => AxisValue::of('V'.$index, Label::of(['en' => 'v'])), range(1, $count)));

        yield 'a code with a space' => [static fn (): Template => Template::of('a b', $name, $pattern, $pricing, [$size]), '"a b" is not the code of a template: 1 to 32 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'a long code' => [static fn (): Template => Template::of(str_repeat('a', 33), $name, $pattern, $pricing, [$size]), \sprintf('"%s" is not the code of a template: 1 to 32 letters, digits, ".", "_" or "-", starting with a letter or digit.', str_repeat('a', 33))];
        yield 'a blank supplier' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$size], supplier: ''), 'The supplier of the template shirt holds 1 to 255 characters, not blank.'];
        yield 'no axis' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, []), 'The template shirt has 1 to 8 axes.'];
        yield 'nine axes' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, array_map(static fn (int $index): Axis => $axis('a'.$index, 1), range(1, 9))), 'The template shirt has 1 to 8 axes.'];
        yield 'an axis twice' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$size, $size]), 'The template shirt lists the axis size twice.'];
        yield 'too many combinations' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$axis('a', 100), $axis('b', 100), $axis('c', 2)]), 'The axes of the template shirt give 20000 combinations, more than 10000.'];
        yield 'a face twice' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$size], [$front, $front]), 'The template shirt lists the face front twice.'];
        yield 'a photo of another face' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [Axis::of('colour', $name, AxisValue::of('BK', $name, photos: ['back' => 'b.jpg']))], [$front]), 'The value BK has a photo of the face back, which the template does not have.'];
        yield 'a placeholder of no axis' => [static fn (): Template => Template::of('shirt', $name, SkuPattern::parse('{design}{colour}'), $pricing, [$size]), 'The SKU pattern of the template shirt names {colour}, which is not one of its axes.'];
        yield 'a price of no value' => [static fn (): Template => Template::of('shirt', $name, $pattern, Pricing::of(Shirts::cad(1))->withPrice('size', 'XL', Shirts::cad(2)), [$size]), 'The pricing gives a price to size XL, which the template does not have.'];
        yield 'an exclusion of no axis' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$size], [], [Exclusion::of(['colour' => ['BK']])]), 'An exclusion of the template shirt names colour BK, which it does not have.'];
        yield 'an exclusion of no value' => [static fn (): Template => Template::of('shirt', $name, $pattern, $pricing, [$size], [], [Exclusion::of(['size' => ['M', 'XL']])]), 'An exclusion of the template shirt names size XL, which it does not have.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedTemplates')]
    public function testATemplateRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testATemplateTakesItsLimits(): void
    {
        $axes = array_map(static fn (int $index): Axis => Axis::of('a'.$index, Label::of(['en' => 'a']), AxisValue::of('V', Label::of(['en' => 'v']))), range(1, 7));
        $axes[] = Axis::of('big', Label::of(['en' => 'big']), ...array_map(static fn (int $index): AxisValue => AxisValue::of('V'.$index, Label::of(['en' => 'v'])), range(1, 500)));
        $wide = Axis::of('wide', Label::of(['en' => 'wide']), ...array_map(static fn (int $index): AxisValue => AxisValue::of('W'.$index, Label::of(['en' => 'w'])), range(1, 20)));
        $pattern = SkuPattern::parse('{design}{template}');

        self::assertCount(8, Template::of(str_repeat('a', 32), Label::of(['en' => 'x']), $pattern, Pricing::of(Shirts::cad(1)), $axes)->axes());
        self::assertCount(2, Template::of('x', Label::of(['en' => 'x']), $pattern, Pricing::of(Shirts::cad(1)), [$axes[7], $wide])->axes());
    }
}
