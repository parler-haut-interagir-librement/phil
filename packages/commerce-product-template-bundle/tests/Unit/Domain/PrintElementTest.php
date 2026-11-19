<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Print\PrintElementKind;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintElement::class)]
#[CoversClass(DiceSlot::class)]
#[CoversClass(PrintElementKind::class)]
final class PrintElementTest extends TestCase
{
    public function testEachKindReadsBackFromItsArray(): void
    {
        $elements = [...LootShirts::layout(), PrintElement::text('back', 'full', 'epitaph', new Box(1, 2, 3, 4), new Colour(16, 32, 48), 20.5, 3, 'Test Sans', ['size' => ['M']], ['tomb' => ['cross', 'stone']], 'glow')];

        foreach ($elements as $element) {
            $data = json_decode(json_encode($element->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            self::assertEquals($element, PrintElement::fromArray($data));
        }
    }

    public function testItsArrayHoldsEveryValue(): void
    {
        self::assertSame([
            'kind' => 'text',
            'face' => 'back',
            'zone' => 'full',
            'option' => 'epitaph',
            'box' => ['x' => 1.0, 'y' => 2.0, 'width' => 3.0, 'height' => 4.0],
            'path' => null,
            'images' => [],
            'slots' => [],
            'font' => 'Test Sans',
            'colour' => '#102030',
            'max_size' => 20.5,
            'min_size' => 3.0,
            'ink' => 'glow',
            'when' => ['size' => ['M']],
            'chosen' => ['ink' => ['glow'], 'tomb' => ['cross']],
        ], PrintElement::text('back', 'full', 'epitaph', new Box(1, 2, 3, 4), new Colour(16, 32, 48), 20.5, 3, 'Test Sans', ['size' => ['M']], ['tomb' => ['cross'], 'ink' => ['glow']], 'glow')->toArray());

        $dice = PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 0, 1, 1), 2, [2 => 'b.png', 1 => 'a.png'])]);
        self::assertSame([['box' => ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0], 'sides' => 2, 'images' => ['a.png', 'b.png']]], $dice->toArray()['slots']);
        self::assertSame([2], $dice->sides());
        $digits = PrintElement::choice('front', 'chest', 'tomb', new Box(0, 0, 1, 1), ['2' => 'b.png', '10' => 'a.png']);
        self::assertSame(['10' => 'a.png', '2' => 'b.png'], $digits->images);
        self::assertEquals($digits, PrintElement::fromArray($digits->toArray()));
        self::assertSame(['cross' => 'c.png', 'stone' => 's.png'], PrintElement::choice('front', 'chest', 'tomb', new Box(0, 0, 1, 1), ['stone' => 's.png', 'cross' => 'c.png'])->images);
    }

    public function testItAppliesToTheValuesAndChoicesItLists(): void
    {
        $element = PrintElement::image('front', 'chest', 'a.png', new Box(0, 0, 1, 1), ['version' => ['O', 'C']], ['ink' => ['glow']]);

        self::assertTrue($element->appliesTo(['version' => 'C', 'size' => 'M'], ['ink' => 'glow']));
        self::assertFalse($element->appliesTo(['version' => 'L', 'size' => 'M'], ['ink' => 'glow']));
        self::assertFalse($element->appliesTo(['version' => 'C'], ['ink' => 'plain']));
        self::assertFalse($element->appliesTo(['version' => 'C'], []));
        self::assertFalse($element->appliesTo([], ['ink' => 'glow']));
        self::assertTrue(PrintElement::image('front', 'chest', 'a.png', new Box(0, 0, 1, 1))->appliesTo([], []));
    }

    public function testASlotGivesTheImageOfEachResult(): void
    {
        $slot = DiceSlot::of(new Box(0, 0, 1, 1), 2, [1 => 'a.png', 2 => 'b.png']);

        self::assertSame('b.png', $slot->image(2));

        $this->expectExceptionObject(InvalidValue::because('A d2 has no result 3.'));

        $slot->image(3);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        $box = new Box(0, 0, 1, 1);

        yield 'a face that is not a code' => [static fn (): PrintElement => PrintElement::image('Front', 'chest', 'a.png', $box), '"Front" is not the code of a face: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a zone that is not a code' => [static fn (): PrintElement => PrintElement::image('front', 'chest-1', 'a.png', $box), '"chest-1" is not the code of a zone: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'an option that is not a code' => [static fn (): PrintElement => PrintElement::text('front', 'chest', '1text'), '"1text" is not the code of a option: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'an ink that is not a name' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, ink: 'Glow'), '"Glow" is not the name of an ink: up to 32 lowercase letters, digits, "_" or "-", starting with a letter.'];
        yield 'a path that leaves the store' => [static fn (): PrintElement => PrintElement::image('front', 'chest', '../a.png', $box), '"../a.png" is not a path of the store of images.'];
        yield 'a path too long' => [static fn (): PrintElement => PrintElement::image('front', 'chest', str_repeat('a', 513), $box), '"'.str_repeat('a', 513).'" is not a path of the store of images.'];
        yield 'a condition on an axis without values' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, ['version' => []]), 'The condition of a print element names, by axis code, a list of one value code or more.'];
        yield 'choices without codes' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['ink' => []]), 'The choices a print element applies to name, by option code, a list of one choice code or more.'];
        yield 'choices of an option that is not a code' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['Ink' => ['glow']]), 'The choices a print element applies to name, by option code, a list of one choice code or more.'];
        yield 'a chosen code that is not one' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['ink' => ['Glow']]), '"Glow" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'a text smaller than its minimum' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', maxSize: 5, minSize: 6), 'The text of the option text is printed from a positive size up to a maximum at least as large, at most 1000 pt; 6 to 5 pt is not that.'];
        yield 'a text of no size' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', minSize: 0), 'The text of the option text is printed from a positive size up to a maximum at least as large, at most 1000 pt; 0 to 72 pt is not that.'];
        yield 'a text too large' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', maxSize: 1001), 'The text of the option text is printed from a positive size up to a maximum at least as large, at most 1000 pt; 6 to 1001 pt is not that.'];
        yield 'a face ending with a newline' => [static fn (): PrintElement => PrintElement::image("front\n", 'chest', 'a.png', $box), "\"front\n\" is not the code of a face: up to 32 lowercase letters, digits or \"_\", starting with a letter."];
        yield 'a path ending with a newline' => [static fn (): PrintElement => PrintElement::image('front', 'chest', "a.png\n", $box), "\"a.png\n\" is not a path of the store of images."];
        yield 'an ink ending with a newline' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, ink: "glow\n"), "\"glow\n\" is not the name of an ink: up to 32 lowercase letters, digits, \"_\" or \"-\", starting with a letter."];
        yield 'an ink with a space' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, ink: 'glow x'), '"glow x" is not the name of an ink: up to 32 lowercase letters, digits, "_" or "-", starting with a letter.'];
        yield 'choices of an option ending with a newline' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ["ink\n" => ['glow']]), 'The choices a print element applies to name, by option code, a list of one choice code or more.'];
        yield 'choices of an option with a space' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['ink x' => ['glow']]), 'The choices a print element applies to name, by option code, a list of one choice code or more.'];
        yield 'a chosen code ending with a newline' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['ink' => ["glow\n"]]), "\"glow\n\" is not the code of a choice: 1 to 64 lowercase letters, digits, \"_\" or \"-\"."];
        yield 'a chosen code with a space' => [static fn (): PrintElement => PrintElement::image('front', 'chest', 'a.png', $box, chosen: ['ink' => ['glow x']]), '"glow x" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'a choice ending with a newline' => [static fn (): PrintElement => PrintElement::choice('front', 'chest', 'tomb', $box, ["cross\n" => 'a.png']), "\"cross\n\" is not the code of a choice: 1 to 64 lowercase letters, digits, \"_\" or \"-\"."];
        yield 'a choice with a space' => [static fn (): PrintElement => PrintElement::choice('front', 'chest', 'tomb', $box, ['cross x' => 'a.png']), '"cross x" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'a font ending with a newline' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', font: "Sans\n"), "\"Sans\n\" is not the name of a font: 1 to 128 characters, without spaces around."];
        yield 'a font ending with a space' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', font: 'Sans '), '"Sans " is not the name of a font: 1 to 128 characters, without spaces around.'];
        yield 'a font with spaces around' => [static fn (): PrintElement => PrintElement::text('front', 'chest', 'text', font: ' Sans'), '" Sans" is not the name of a font: 1 to 128 characters, without spaces around.'];
        yield 'a choice element without images' => [static fn (): PrintElement => PrintElement::choice('front', 'chest', 'tomb', $box, []), 'The choice element of the option tomb has the image of one choice at least.'];
        yield 'a choice that is not a code' => [static fn (): PrintElement => PrintElement::choice('front', 'chest', 'tomb', $box, ['Cross' => 'a.png']), '"Cross" is not the code of a choice: 1 to 64 lowercase letters, digits, "_" or "-".'];
        yield 'a choice image outside the store' => [static fn (): PrintElement => PrintElement::choice('front', 'chest', 'tomb', $box, ['cross' => '/a.png']), '"/a.png" is not a path of the store of images.'];
        yield 'a dice element without slots' => [static fn (): PrintElement => PrintElement::dice('front', 'chest', 'loot', []), 'The dice element of the option loot has 1 to 64 slots.'];
        yield 'a dice element of too many slots' => [static fn (): PrintElement => PrintElement::dice('front', 'chest', 'loot', array_fill(0, 65, DiceSlot::of($box, 2, [1 => 'a.png', 2 => 'b.png']))), 'The dice element of the option loot has 1 to 64 slots.'];
        yield 'a slot of a die of one face' => [static fn (): DiceSlot => DiceSlot::of($box, 1, [1 => 'a.png']), 'A die has 2 to 20 faces, not 1.'];
        yield 'a slot of a die of 21 faces' => [static fn (): DiceSlot => DiceSlot::of($box, 21, array_combine(range(1, 21), array_fill(0, 21, 'a.png'))), 'A die has 2 to 20 faces, not 21.'];
        yield 'a slot missing a result' => [static fn (): DiceSlot => DiceSlot::of($box, 3, [1 => 'a.png', 3 => 'c.png']), 'A d3 slot has one image for each result from 1 to 3.'];
        yield 'a slot image outside the store' => [static fn (): DiceSlot => DiceSlot::of($box, 2, [1 => 'a.png', 2 => '../b.png']), '"../b.png" is not a path of the store of images.'];
    }

    /**
     * @param callable(): mixed $make
     */
    #[DataProvider('refusals')]
    public function testWhatIsNotAnElementIsRefused(callable $make, string $message): void
    {
        $this->expectExceptionObject(InvalidValue::because($message));

        $make();
    }

    public function testTheBoundsOfAnElementAreAccepted(): void
    {
        $box = new Box(0, 0, 1, 1);
        self::assertSame(1000.0, PrintElement::text('front', 'chest', 'text', maxSize: 1000, minSize: 1000)->maxSize);
        self::assertCount(64, PrintElement::dice('front', 'chest', 'loot', array_fill(0, 64, DiceSlot::of($box, 2, [1 => 'a.png', 2 => 'b.png'])))->slots);
        self::assertSame(20, DiceSlot::of($box, 20, array_combine(range(1, 20), array_fill(0, 20, 'a.png')))->sides);
        self::assertSame(str_repeat('a', 512), PrintElement::image('front', 'chest', str_repeat('a', 512), $box)->path);
        self::assertSame(str_repeat('a', 32), PrintElement::image(str_repeat('a', 32), 'chest', 'a.png', $box)->face);
    }

    public function testAFontIsCountedInCharactersAndEveryElementStartsBlack(): void
    {
        $box = new Box(0, 0, 1, 1);
        $dice = DiceSlot::of($box, 2, [1 => 'a.png', 2 => 'b.png']);

        self::assertSame(str_repeat('é', 128), PrintElement::text('front', 'chest', 'text', font: str_repeat('é', 128))->font);

        foreach ([
            PrintElement::image('front', 'chest', 'a.png', $box),
            PrintElement::text('front', 'chest', 'text'),
            PrintElement::choice('front', 'chest', 'tomb', $box, ['cross' => 'a.png']),
            PrintElement::dice('front', 'chest', 'loot', [$dice]),
        ] as $element) {
            self::assertSame('#000000', $element->colour->toHex(), $element->kind->value);
        }
    }

    public function testASlotReadsItsImagesInOrderAndAConditionKeepsEveryAxisAndCodeList(): void
    {
        $dice = PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 0, 1, 1), 2, [1 => 'a.png', 2 => 'b.png'])])->toArray();
        $slot = ['box' => ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0], 'sides' => 2, 'images' => ['x' => 'a.png', 'y' => 'b.png']];
        $read = PrintElement::fromArray(['slots' => [$slot], 'when' => ['version' => ['k' => 'O', 'l' => 'C'], 'size' => ['M']], 'chosen' => ['ink' => ['glow']]] + $dice);

        self::assertSame('b.png', $read->slots[0]->image(2));
        self::assertSame(['version' => ['O', 'C'], 'size' => ['M']], $read->toArray()['when']);
        self::assertSame(['ink' => ['glow']], $read->toArray()['chosen']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unreadable(): iterable
    {
        $image = PrintElement::image('front', 'chest', 'a.png', new Box(0, 0, 1, 1))->toArray();
        $dice = PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 0, 1, 1), 2, [1 => 'a.png', 2 => 'b.png'])])->toArray();
        $choice = PrintElement::choice('front', 'chest', 'tomb', new Box(0, 0, 1, 1), ['cross' => 'c.png'])->toArray();
        $text = PrintElement::text('front', 'chest', 'text')->toArray();

        yield 'an unknown kind' => [['kind' => 'video'] + $image, '"video" is not a valid backing value for enum '.PrintElementKind::class];
        yield 'a face that is not a string' => [['face' => 1] + $image, 'face is not a string'];
        yield 'a condition that is not an object' => [['when' => 'all'] + $image, 'a condition is an object'];
        yield 'a condition of codes by index' => [['when' => [['O']]] + $image, 'a condition lists codes by code'];
        yield 'a condition of numbers' => [['chosen' => ['ink' => [1]]] + $image, 'a code is a string'];
        yield 'a box that is not an object' => [['box' => 'all'] + $image, 'a box is an object'];
        yield 'a box of strings' => [['box' => ['x' => '0', 'y' => 0, 'width' => 1, 'height' => 1]] + $image, 'x is not a number'];
        yield 'a box outside its zone' => [['box' => ['x' => -1, 'y' => 0, 'width' => 1, 'height' => 1]] + $image, 'A box starts inside its zone; it starts at -1 × 0 mm.'];
        yield 'slots that are not a list' => [['slots' => 'six'] + $dice, 'slots is not a list'];
        yield 'a slot without its faces' => [['slots' => [['box' => $dice['box'], 'images' => []]]] + $dice, 'a slot is a box, a number of faces and its images'];
        yield 'a slot image that is not a path' => [['slots' => [['box' => ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1], 'sides' => 2, 'images' => [1, 2]]]] + $dice, 'an image is a path'];
        yield 'images that are not an object' => [['images' => 'c.png'] + $choice, 'images is not an object'];
        yield 'an image that is not a path' => [['images' => ['cross' => 1]] + $choice, 'an image is a path'];
        yield 'a font that is not a string' => [['font' => 1] + $text, 'font is not a string'];
        yield 'a colour that is not one' => [['colour' => 'red'] + $text, 'A colour is written #RRGGBB or #RRGGBBAA; "red" given.'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('unreadable')]
    public function testAnArrayNoCodeOfTheModuleWroteIsRefused(array $data, string $reason): void
    {
        $this->expectExceptionObject(TemplateRefused::unreadable('An element of a print layout', $reason));

        PrintElement::fromArray($data);
    }
}
