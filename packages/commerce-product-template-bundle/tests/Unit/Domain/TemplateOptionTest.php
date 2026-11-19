<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateOption::class)]
#[CoversClass(TemplateRefused::class)]
final class TemplateOptionTest extends TestCase
{
    /**
     * @return iterable<string, array{TemplateOption}>
     */
    public static function options(): iterable
    {
        yield 'a text' => [TemplateOption::of(OptionDefinition::text('caption', 'Caption', TextConstraints::of(24, 2, "\\p{L} '", 'Serif Bold', PrintZone::of('bottom', 20, 180, 160, 15)), true, Shirts::cad(500)))];
        yield 'a text drawn or picked' => [TemplateOption::of(OptionDefinition::text('epitaph', 'Epitaph', TextConstraints::of(80, sources: [TextSource::List, TextSource::Drawn], entries: ['Here lies', 'Rest']), drawer: 'epitaphs'), ['version' => ['L']])];
        yield 'a choice' => [TemplateOption::of(OptionDefinition::choice('tomb', 'Tomb', [OptionChoice::of('cross', 'Cross', 'tombs/cross.png'), OptionChoice::of('stone', 'Stone', supplement: Shirts::cad(200))], true))];
        yield 'an ink' => [TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark', supplement: Shirts::cad(300))]), ['colour' => ['BK', 'NV']])];
        yield 'a draw' => [TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-6d6', Shirts::cad(1_000)), ['version' => ['L']])];
    }

    #[DataProvider('options')]
    public function testAnOptionReadsBackThroughJson(TemplateOption $option): void
    {
        $json = json_decode(json_encode($option->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        self::assertEquals($option, TemplateOption::fromArray($json));
    }

    public function testAnOptionIsWrittenAsScalars(): void
    {
        $text = TemplateOption::of(OptionDefinition::text('caption', 'Caption', TextConstraints::of(24, 2, 'a-z', 'Serif', PrintZone::of('bottom', 1, 2, 3, 4)), true, Shirts::cad(500)), ['size' => ['M']]);

        self::assertSame([
            'code' => 'caption',
            'label' => 'Caption',
            'kind' => 'text',
            'required' => true,
            'supplement' => ['amount' => 500, 'currency' => 'CAD'],
            'text' => ['min_length' => 2, 'max_length' => 24, 'characters' => 'a-z', 'font' => 'Serif', 'zone' => ['name' => 'bottom', 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4], 'sources' => ['free'], 'entries' => []],
            'choices' => [],
            'drawer' => null,
            'when' => ['size' => ['M']],
        ], $text->toArray());

        $choice = TemplateOption::of(OptionDefinition::choice('tomb', 'Tomb', [OptionChoice::of('stone', 'Stone', 'a.png', Shirts::cad(200))]));

        self::assertSame([['code' => 'stone', 'label' => 'Stone', 'image' => 'a.png', 'supplement' => ['amount' => 200, 'currency' => 'CAD']]], $choice->toArray()['choices']);
        self::assertNull($choice->toArray()['text']);
        self::assertNull($choice->toArray()['supplement']);
        $plain = TemplateOption::of(OptionDefinition::text('t', 'T', TextConstraints::of(5)))->toArray()['text'];
        self::assertIsArray($plain);
        self::assertNull($plain['zone']);
    }

    public function testListsReadBackAsListsWhateverTheirKeys(): void
    {
        $text = TemplateOption::of(OptionDefinition::text('epitaph', 'Epitaph', TextConstraints::of(80, sources: [TextSource::List, TextSource::Drawn], entries: ['Here lies', 'Rest']), drawer: 'epitaphs'));
        $array = $text->toArray();
        self::assertIsArray($array['text']);
        $array['text'] = ['sources' => ['a' => 'list', 'b' => 'drawn'], 'entries' => ['a' => 'Here lies', 'b' => 'Rest']] + $array['text'];

        self::assertEquals($text, TemplateOption::fromArray($array));

        $ink = TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow'), OptionChoice::of('gold', 'Gold')]));
        $array = $ink->toArray();
        self::assertIsArray($array['choices']);
        $array['choices'] = array_combine(['a', 'b'], $array['choices']);

        self::assertEquals($ink, TemplateOption::fromArray($array));
    }

    public function testAnOptionIsOfferedWhereItsConditionMatches(): void
    {
        $option = TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'), ['version' => ['L'], 'size' => ['M', 'L']]);

        self::assertTrue($option->isOfferedOn(['version' => 'L', 'size' => 'M', 'colour' => 'BK']));
        self::assertFalse($option->isOfferedOn(['version' => 'O', 'size' => 'M']));
        self::assertFalse($option->isOfferedOn(['version' => 'L', 'size' => 'S']));
        self::assertFalse($option->isOfferedOn(['size' => 'M']));
        self::assertTrue(TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'))->isOfferedOn([]));
    }

    public function testAConditionThatIsNotOneIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::because('The condition of the option loot names, by axis code, a list of one value code or more.'));

        TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'), ['version' => []]);
    }

    public function testOptionsAreKeptOnceEach(): void
    {
        $loot = TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot'));
        $ink = TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow')]));

        self::assertSame([$loot->toArray(), $ink->toArray()], TemplateOption::listToArray([$loot, $ink]));

        $this->expectExceptionObject(InvalidValue::because('The option loot is declared twice.'));

        TemplateOption::listToArray([$loot, $ink, $loot]);
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<array-key, mixed>, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'no code' => [static fn (array $a): array => ['code' => 1] + $a, 'code is not a string'];
        yield 'no label' => [static fn (array $a): array => ['label' => null] + $a, 'label is not a string'];
        yield 'an unknown kind' => [static fn (array $a): array => ['kind' => 'colour'] + $a, '"colour" is not a valid backing value for enum'];
        yield 'an amount' => [static fn (array $a): array => ['kind' => 'amount'] + $a, 'an amount is chosen on an order line, never in a template'];
        yield 'required of no boolean' => [static fn (array $a): array => ['required' => 1] + $a, 'required is not a boolean'];
        yield 'a supplement of no object' => [static fn (array $a): array => ['supplement' => 5] + $a, 'a supplement is an amount and a currency'];
        yield 'a supplement without amount' => [static fn (array $a): array => ['supplement' => ['currency' => 'CAD']] + $a, 'a supplement is an amount and a currency'];
        yield 'a supplement without currency' => [static fn (array $a): array => ['supplement' => ['amount' => 1]] + $a, 'a supplement is an amount and a currency'];
        yield 'an unknown currency' => [static fn (array $a): array => ['supplement' => ['amount' => 1, 'currency' => 'XXZ']] + $a, 'XXZ'];
        yield 'a drawer of no string' => [static fn (array $a): array => ['drawer' => 1] + $a, 'drawer is not a string'];
        yield 'a text without constraints' => [static fn (array $a): array => ['text' => null] + $a, 'a text option has its constraints'];
        yield 'a text without minimum' => [static fn (array $a): array => self::text($a, ['min_length' => '1']), 'a text option has its constraints'];
        yield 'a text without maximum' => [static fn (array $a): array => self::text($a, ['max_length' => null]), 'a text option has its constraints'];
        yield 'a text without sources' => [static fn (array $a): array => self::text($a, ['sources' => 'free']), 'a text option has its constraints'];
        yield 'a text without entries' => [static fn (array $a): array => self::text($a, ['entries' => null]), 'a text option has its constraints'];
        yield 'a source of no string' => [static fn (array $a): array => self::text($a, ['sources' => [1]]), 'a source is a string'];
        yield 'an unknown source' => [static fn (array $a): array => self::text($a, ['sources' => ['typed']]), '"typed" is not a valid backing value for enum'];
        yield 'an entry of no string' => [static fn (array $a): array => self::text($a, ['sources' => ['list'], 'entries' => [1]]), 'an entry is a string'];
        yield 'characters of no string' => [static fn (array $a): array => self::text($a, ['characters' => 1]), 'characters is not a string'];
        yield 'a font of no string' => [static fn (array $a): array => self::text($a, ['font' => 1]), 'font is not a string'];
        yield 'a zone of no object' => [static fn (array $a): array => self::text($a, ['zone' => 'bottom']), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a zone without name' => [static fn (array $a): array => self::text($a, ['zone' => ['x' => 1, 'y' => 1, 'width' => 1, 'height' => 1]]), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a zone without x' => [static fn (array $a): array => self::text($a, ['zone' => ['name' => 'z', 'y' => 1, 'width' => 1, 'height' => 1]]), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a zone without y' => [static fn (array $a): array => self::text($a, ['zone' => ['name' => 'z', 'x' => 1, 'width' => 1, 'height' => 1]]), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a zone without width' => [static fn (array $a): array => self::text($a, ['zone' => ['name' => 'z', 'x' => 1, 'y' => 1, 'height' => 1]]), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a zone without height' => [static fn (array $a): array => self::text($a, ['zone' => ['name' => 'z', 'x' => 1, 'y' => 1, 'width' => 1]]), 'a zone is a name and a rectangle of whole millimetres'];
        yield 'a text refused by the core' => [static fn (array $a): array => self::text($a, ['max_length' => 1001]), 'A text holds from 1 to 1000 characters'];
        yield 'a condition of no object' => [static fn (array $a): array => ['when' => 'x'] + $a, 'when is not an object'];
        yield 'a condition refused' => [static fn (array $a): array => ['when' => ['size' => 'M']] + $a, 'The condition of an option names, by axis code, a list of one value code or more.'];
    }

    public function testAnAmountIsNeverATemplateOption(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('The option price is an amount, chosen on an order line, never in a template.');

        TemplateOption::of(OptionDefinition::amount('price', 'Price', AmountRange::of(Shirts::cad(0), Shirts::cad(10))));
    }

    /**
     * @param callable(array<string, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('unreadable')]
    public function testAnArrayNoCodeWroteIsRefused(callable $change, string $reason): void
    {
        $option = TemplateOption::of(OptionDefinition::text('caption', 'Caption', TextConstraints::of(24, font: 'Serif', zone: PrintZone::of('bottom', 1, 2, 3, 4)), supplement: Shirts::cad(1)));

        try {
            TemplateOption::fromArray($change($option->toArray()));
            self::fail('The array is refused.');
        } catch (TemplateRefused $templateRefused) {
            self::assertStringStartsWith('An option cannot be read back: ', $templateRefused->getMessage());
            self::assertStringContainsString($reason, $templateRefused->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unreadableChoices(): iterable
    {
        $choice = ['code' => 'glow', 'label' => 'Glow', 'image' => null, 'supplement' => null];

        yield 'choices of no list' => [['choices' => 'glow'], 'choices is not a list'];
        yield 'a choice of no object' => [['choices' => ['glow']], 'a choice is an object'];
        yield 'a choice without code' => [['choices' => [['code' => null] + $choice]], 'code is not a string'];
        yield 'a choice without label' => [['choices' => [['label' => null] + $choice]], 'label is not a string'];
        yield 'an image of no string' => [['choices' => [['image' => 1] + $choice]], 'image is not a string'];
        yield 'a supplement of no money' => [['choices' => [['supplement' => 1] + $choice]], 'a supplement is an amount and a currency'];
        yield 'no choice' => [['choices' => []], 'The option ink has at least one choice.'];
        yield 'a draw without drawer' => [['kind' => 'draw', 'drawer' => null], 'a draw names its drawer'];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('unreadableChoices')]
    public function testAChoiceNoCodeWroteIsRefused(array $change, string $reason): void
    {
        $option = TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow')]));

        $this->expectExceptionObject(TemplateRefused::unreadable('An option', $reason));

        TemplateOption::fromArray($change + $option->toArray());
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<string, mixed>    $text
     *
     * @return array<array-key, mixed>
     */
    private static function text(array $array, array $text): array
    {
        $inner = $array['text'];
        self::assertIsArray($inner);
        $array['text'] = $text + $inner;

        return $array;
    }
}
