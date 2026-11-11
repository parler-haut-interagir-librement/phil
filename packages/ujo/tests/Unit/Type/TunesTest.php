<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Type;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Dictionary;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Tests\Unit\UjoTestCase;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Tunes::class)]
#[CoversClass(Dictionary::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(Html::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(BlockTypeRegistry::class)]
#[CoversClass(TuneRegistry::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Rendering::class)]
#[CoversClass(TextRenderer::class)]
#[CoversClass(ListRenderer::class)]
#[CoversClass(MediaRenderer::class)]
#[CoversClass(LayoutRenderer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
final class TunesTest extends UjoTestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function tunes(): iterable
    {
        yield 'alignment' => [['alignment' => ['alignment' => 'center']], ['alignment' => ['alignment' => 'center']]];
        yield 'alignmentTune' => [['alignmentTune' => ['alignment' => 'left']], ['alignment' => ['alignment' => 'left']]];
        yield 'anchor' => [['anchorTune' => ['anchor' => 'a-1']], ['anchor' => ['anchor' => 'a-1']]];
        yield 'textVariant' => [['textVariant' => 'citation'], ['textVariant' => 'citation']];
        yield 'notice' => [['noticeTune' => ['style' => 'spoiler']], ['notice' => ['style' => 'spoiler', 'caption' => '']]];
        yield 'notice with caption' => [['notice' => ['style' => 'info', 'caption' => 'c']], ['notice' => ['style' => 'info', 'caption' => 'c']]];
        yield 'indent' => [['indentTune' => ['indentLevel' => 2]], ['indent' => ['indentLevel' => 2]]];
        yield 'layout' => [['layout' => ['position' => 'absolute', 'top' => '20px', 'right' => '-3cm', 'bottom' => '0', 'left' => 'auto', 'width' => '50%', 'height' => '1.5em', 'wrap' => 'left', 'zIndex' => 3]], ['layout' => ['position' => 'absolute', 'top' => '20px', 'right' => '-3cm', 'bottom' => '0', 'left' => 'auto', 'width' => '50%', 'height' => '1.5em', 'wrap' => 'left', 'zIndex' => 3]]];
        yield 'empty layout' => [['layout' => []], ['layout' => []]];
        yield 'mask' => [['mask' => ['shape' => 'center']], ['mask' => ['shape' => 'center', 'variant' => 1, 'rotation' => 0]]];
        yield 'full mask' => [['mask' => ['shape' => 'corner', 'variant' => 3, 'offsetX' => '-25%', 'offsetY' => '10.5%', 'rotation' => 90]], ['mask' => ['shape' => 'corner', 'variant' => 3, 'offsetX' => '-25%', 'offsetY' => '10.5%', 'rotation' => 90]]];
        yield 'toc' => [['toc' => ['include' => false]], ['toc' => ['include' => false]]];
        yield 'variant' => [['variant' => ['name' => 'classTable']], ['variant' => ['name' => 'classTable', 'frame' => false, 'decoration' => false]]];
        yield 'full variant' => [['variant' => ['name' => 'v', 'frame' => true, 'decoration' => true]], ['variant' => ['name' => 'v', 'frame' => true, 'decoration' => true]]];
    }

    /**
     * @param array<string, mixed> $tunes
     * @param array<string, mixed> $expected
     */
    #[DataProvider('tunes')]
    public function testTunesAreReadUnderTheirCanonicalName(array $tunes, array $expected): void
    {
        self::assertSame($expected, self::normalize('paragraph', ['text' => 't'], $tunes)->tunes);
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, list<string>}>
     */
    public static function choices(): iterable
    {
        yield 'alignment' => ['alignment', 'alignment', [], ['left', 'center', 'right', 'justify']];
        yield 'notice' => ['notice', 'style', [], ['info', 'warning', 'spoiler']];
        yield 'position' => ['layout', 'position', [], ['absolute', 'relative']];
        yield 'wrap' => ['layout', 'wrap', [], ['left', 'right']];
        yield 'mask' => ['mask', 'shape', [], ['center', 'edge', 'corner']];
    }

    /**
     * @param array<array-key, mixed> $base
     * @param list<string>            $choices
     */
    #[DataProvider('choices')]
    public function testAChoiceOfATuneIsOneOfItsList(string $tune, string $field, array $base, array $choices): void
    {
        foreach ($choices as $choice) {
            self::assertSame($choice, $this->array(self::normalize('paragraph', ['text' => 't'], [$tune => [...$base, $field => $choice]])->tunes[$tune])[$field]);
        }

        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.'.$tune.'.'.$field.': one of "'.implode('", "', $choices).'" is expected.', 'paragraph', ['text' => 't'], [$tune => [...$base, $field => 'nope']]);
    }

    public function testTheTextVariantIsOneOfItsList(): void
    {
        foreach (['', 'call-out', 'citation', 'details'] as $variant) {
            self::assertSame($variant, self::normalize('paragraph', ['text' => 't'], ['textVariant' => $variant])->tunes['textVariant']);
        }

        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.textVariant: one of "", "call-out", "citation", "details" is expected.', 'paragraph', ['text' => 't'], ['textVariant' => 'x']);
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, int, int}>
     */
    public static function bounds(): iterable
    {
        yield 'indent' => ['indent', 'indentLevel', [], 0, 8];
        yield 'zIndex' => ['layout', 'zIndex', [], -10, 100];
        yield 'mask variant' => ['mask', 'variant', ['shape' => 'edge'], 1, 99];
        yield 'mask rotation' => ['mask', 'rotation', ['shape' => 'edge'], -360, 360];
    }

    /**
     * @param array<array-key, mixed> $base
     */
    #[DataProvider('bounds')]
    public function testAWholeNumberOfATuneStaysWithinItsBounds(string $tune, string $field, array $base, int $min, int $max): void
    {
        $at = 'The UJO document is invalid at blocks[0].tunes.'.$tune.'.'.$field.': ';

        foreach ([$min, $max] as $bound) {
            self::assertSame($bound, $this->array(self::normalize('paragraph', ['text' => 't'], [$tune => [...$base, $field => $bound]])->tunes[$tune])[$field]);
        }

        self::assertInvalid($at.\sprintf('%d is below the minimum %d.', $min - 1, $min), 'paragraph', ['text' => 't'], [$tune => [...$base, $field => $min - 1]]);
        self::assertInvalid($at.\sprintf('%d is above the maximum %d.', $max + 1, $max), 'paragraph', ['text' => 't'], [$tune => [...$base, $field => $max + 1]]);
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, list<string>, list<string>}>
     */
    public static function patterns(): iterable
    {
        yield 'anchor' => ['anchor', 'anchor', [], ['a', 'A-b_9', 'a'.str_repeat('b', 63)], ['', '9a', 'a b', 'a'.str_repeat('b', 64)]];
        yield 'length' => ['layout', 'top', [], ['0', 'auto', '20px', '-3cm', '1.5em', '2.125rem', '50%', '10mm', '1in', '12pt', '3ch', '10vw', '10vh', '9999px'], ['', '20', '20 px', '10000px', '1.5555em', 'calc(1px)', '1px;color:red', 'autoo', '00']];
        yield 'percentage' => ['mask', 'offsetX', ['shape' => 'edge'], ['0%', '-25%', '100%', '12.5%'], ['', '25', '1000%', '1.255%', '25 %']];
        yield 'variant name' => ['variant', 'name', [], ['classTable'], ['class table']];
        yield 'language' => ['language', 'language', [], ['en', 'fr-CA', 'sr-Latn-RS', 'x-elvish', 'en'.str_repeat('-abcdefgh', 6).'-abcdefg'], ['', 'e1', 'fr_CA', 'english9']];
    }

    /**
     * @param array<array-key, mixed> $base
     * @param list<string>            $valid
     * @param list<string>            $invalid
     */
    #[DataProvider('patterns')]
    public function testATextOfATuneIsOfItsForm(string $tune, string $field, array $base, array $valid, array $invalid): void
    {
        foreach ($valid as $text) {
            self::assertSame($text, $this->array(self::normalize('paragraph', ['text' => 't'], [$tune => [...$base, $field => $text]])->tunes[$tune])[$field]);
        }

        foreach ($invalid as $text) {
            self::assertInvalid('The UJO document is invalid at blocks[0].tunes.'.$tune.'.'.$field.': "'.$text.'" is not of the expected form.', 'paragraph', ['text' => 't'], [$tune => [...$base, $field => $text]]);
        }
    }

    public function testRequiredValuesAndLengthsOfTunes(): void
    {
        foreach (['alignment' => 'alignment', 'anchor' => 'anchor', 'notice' => 'style', 'indent' => 'indentLevel', 'mask' => 'shape', 'toc' => 'include', 'variant' => 'name'] as $tune => $field) {
            self::assertInvalid('The UJO document is invalid at blocks[0].tunes.'.$tune.'.'.$field.': this value is required.', 'paragraph', ['text' => 't'], [$tune => []]);
        }

        self::assertSame(str_repeat('c', 200), $this->array(self::normalize('paragraph', ['text' => 't'], ['notice' => ['style' => 'info', 'caption' => str_repeat('c', 200)]])->tunes['notice'])['caption']);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.notice.caption: the text is longer than 200 characters.', 'paragraph', ['text' => 't'], ['notice' => ['style' => 'info', 'caption' => str_repeat('c', 201)]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.toc.include: true or false is expected.', 'paragraph', ['text' => 't'], ['toc' => ['include' => 1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.variant.frame: true or false is expected.', 'paragraph', ['text' => 't'], ['variant' => ['name' => 'a', 'frame' => 1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.variant.decoration: true or false is expected.', 'paragraph', ['text' => 't'], ['variant' => ['name' => 'a', 'decoration' => 1]]);
    }

    public function testTheLanguageDataAndNoteOfABlock(): void
    {
        $data = array_combine(array_map(static fn (int $index): string => 'k'.$index, range(1, 32)), array_fill(0, 32, str_repeat('é', 1000)));
        $block = self::normalize('paragraph', ['text' => 't'], ['language' => ['language' => 'he', 'direction' => 'rtl'], 'data' => $data, 'note' => ['text' => str_repeat('n', 10_000)]]);

        self::assertSame(['language' => 'he', 'direction' => 'rtl'], $block->tunes['language']);
        self::assertSame($data, $block->tunes['data']);
        self::assertSame([], self::normalize('paragraph', ['text' => 't'], ['data' => []])->tunes['data']);
        self::assertSame(['a-1' => 'x', 'b' => ''], self::normalize('paragraph', ['text' => 't'], ['data' => ['a-1' => 'x', 'b' => '']])->tunes['data']);
        self::assertSame('k'.str_repeat('a', 63), array_key_first($this->array(self::normalize('paragraph', ['text' => 't'], ['data' => ['k'.str_repeat('a', 63) => 'x']])->tunes['data'])));
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.language.direction: one of "ltr", "rtl", "auto" is expected.', 'paragraph', ['text' => 't'], ['language' => ['language' => 'he', 'direction' => 'up']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.language.language: this value is required.', 'paragraph', ['text' => 't'], ['language' => []]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: more than 32 entries.', 'paragraph', ['text' => 't'], ['data' => [...$data, 'k33' => 'x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data.k1: a text of at most 1000 characters is expected.', 'paragraph', ['text' => 't'], ['data' => ['k1' => str_repeat('é', 1001)]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data.k1: a text of at most 1000 characters is expected.', 'paragraph', ['text' => 't'], ['data' => ['k1' => 1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: "Key" is not a name of the expected form.', 'paragraph', ['text' => 't'], ['data' => ['Key' => 'x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: "1a" is not a name of the expected form.', 'paragraph', ['text' => 't'], ['data' => ['1a' => 'x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: "k'.str_repeat('a', 64).'" is not a name of the expected form.', 'paragraph', ['text' => 't'], ['data' => ['k'.str_repeat('a', 64) => 'x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: "a'."\n".'" is not a name of the expected form.', 'paragraph', ['text' => 't'], ['data' => ["a\n" => 'x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: an object is expected.', 'paragraph', ['text' => 't'], ['data' => ['x']]);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.data: an object is expected.', 'paragraph', ['text' => 't'], ['data' => 'x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.note.text: the text is longer than 10000 characters.', 'paragraph', ['text' => 't'], ['note' => ['text' => str_repeat('n', 10_001)]]);
    }

    public function testAnUnknownTuneIsRefused(): void
    {
        $this->expectExceptionObject(UnknownBlockType::tune('sparkle', 'blocks[0].tunes.sparkle'));

        self::normalize('paragraph', ['text' => 't'], ['sparkle' => true]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }
}
