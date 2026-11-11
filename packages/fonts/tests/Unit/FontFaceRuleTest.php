<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Axis;
use Phil\Fonts\Css\FontDisplay;
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\Exception\InvalidFontFace;
use Phil\Fonts\Font;
use Phil\Fonts\FontFile;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Format;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;
use Phil\Fonts\Tests\Unit\Fixture\FontBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontFaceRule::class)]
#[CoversClass(InvalidFontFace::class)]
final class FontFaceRuleTest extends TestCase
{
    /**
     * @param array<int, string> $names
     */
    private function names(array $names): Names
    {
        return new Names(array_map(static fn (int $id, string $value): NameRecord => new NameRecord(3, 1, 0x0409, $id, $value), array_keys($names), $names));
    }

    public function testWritesTheRuleOfAStaticFace(): void
    {
        $font = new Font(names: $this->names([1 => 'Example Sans', 4 => 'Example Sans Bold Italic', 6 => 'ExampleSans-BoldItalic']), weight: 700, widthClass: 4, style: FontStyle::Italic);

        self::assertSame(<<<'CSS'
            @font-face {
                font-family: "Example Sans";
                src: local("Example Sans Bold Italic"), local("ExampleSans-BoldItalic"), url("/fonts/example.woff2") format("woff2"), url("/fonts/example.woff") format("woff");
                font-weight: 700;
                font-style: italic;
                font-stretch: 87.5%;
                font-display: swap;
            }
            CSS, FontFaceRule::for($font)->withUrl('/fonts/example.woff2', Format::Woff2)->withUrl('/fonts/example.woff', Format::Woff)->toCss());
    }

    public function testWritesTheRangesOfAVariableFace(): void
    {
        $font = new Font(
            names: $this->names([16 => 'Example Flex', 1 => 'Example Flex Regular']),
            axes: [new Axis('wght', 100, 400, 900, 256), new Axis('wdth', 75, 100, 112.5, 257)],
        );

        self::assertSame(<<<'CSS'
            @font-face {
                font-family: "Example Flex";
                src: url("flex.ttf") format("truetype");
                font-weight: 100 900;
                font-style: normal;
                font-stretch: 75% 112.5%;
                font-display: optional;
            }
            CSS, FontFaceRule::for($font)->withUrl('flex.ttf', Format::TrueType)->withDisplay(FontDisplay::Optional)->toCss());
    }

    public function testWritesTheUnicodeRangeOfTheFontOrTheOneGiven(): void
    {
        $font = new Font(names: $this->names([1 => 'Ranged']), unicodeRanges: [[0x20, 0x7E], [0xE9, 0xE9], [0x1F600, 0x1F64F]]);
        $rule = FontFaceRule::for($font)->withUrl('r.woff2', Format::Woff2);

        self::assertStringContainsString("    unicode-range: U+20-7E, U+E9, U+1F600-1F64F;\n}", $rule->withUnicodeRange()->toCss());
        self::assertStringContainsString("    unicode-range: U+0-FF;\n}", $rule->withUnicodeRange([[0, 0xFF]])->toCss());
        self::assertStringNotContainsString('unicode-range', $rule->toCss());
        self::assertStringNotContainsString('unicode-range', FontFaceRule::for(new Font(names: $this->names([1 => 'None', 6 => 'None'])))->withUnicodeRange()->toCss());
    }

    public function testEachChangeKeepsTheOthers(): void
    {
        $font = new Font(names: $this->names([1 => 'Kept', 6 => 'Kept-Regular']), unicodeRanges: [[0x41, 0x41]]);
        $rule = FontFaceRule::for($font)->withUrl('k.woff', Format::Woff)->withDisplay(FontDisplay::Block)->withUnicodeRange();
        $expected = static fn (string $family, string $src): string => "@font-face {\n    font-family: \"{$family}\";\n    src: {$src};\n    font-weight: 400;\n    font-style: normal;\n    font-stretch: 100%;\n    font-display: block;\n    unicode-range: U+41;\n}";

        self::assertSame($expected('Other', 'local("Kept-Regular"), url("k.woff") format("woff")'), $rule->withFamily('Other')->toCss());
        self::assertSame($expected('Kept', 'url("k.woff") format("woff")'), $rule->withoutLocal()->toCss());
        self::assertSame($expected('Kept', 'local("Kept-Regular"), url("k.woff") format("woff"), url("k.ttf") format("truetype")'), $rule->withUrl('k.ttf', Format::TrueType)->toCss());
        self::assertSame(str_replace('block', 'fallback', $expected('Kept', 'local("Kept-Regular"), url("k.woff") format("woff")')), $rule->withDisplay(FontDisplay::Fallback)->toCss());
        self::assertSame(str_replace('U+41', 'U+42', $expected('Kept', 'local("Kept-Regular"), url("k.woff") format("woff")')), $rule->withUnicodeRange([[0x42, 0x42]])->toCss());
    }

    public function testRoundsRangesToThreeDecimals(): void
    {
        $font = new Font(names: self::names([1 => 'Precise', 6 => 'Precise']), axes: [new Axis('wdth', 62.5054, 100, 112.5054, 256)]);

        self::assertStringContainsString("    font-stretch: 62.505% 112.505%;\n", FontFaceRule::for($font)->toCss());
    }

    public function testWritesTheFullNameAloneWhenThePostScriptNameIsMissing(): void
    {
        self::assertStringContainsString("    src: local(\"Only Full\");\n", FontFaceRule::for(new Font(names: self::names([1 => 'F', 4 => 'Only Full'])))->toCss());
        self::assertStringContainsString("    src: local(\"OnlyPs\");\n", FontFaceRule::for(new Font(names: self::names([1 => 'F', 6 => 'OnlyPs'])))->toCss());
    }

    public function testWritesOneLocalSourceWhenBothNamesAgree(): void
    {
        $font = new Font(names: $this->names([1 => 'Same', 4 => 'Same', 6 => 'Same']), style: FontStyle::Oblique);

        self::assertStringContainsString("    src: local(\"Same\");\n", FontFaceRule::for($font)->toCss());
        self::assertStringContainsString("    font-style: oblique;\n", FontFaceRule::for($font)->toCss());
    }

    public function testEscapesWhatCouldEndTheStringTheRuleOrTheStyleElement(): void
    {
        $font = new Font(names: $this->names([1 => "Evil \"Font\" \\ </style>\n\x7F"]));
        $css = FontFaceRule::for($font)->withUrl('a")</style><script>', Format::Woff2)->toCss();

        self::assertStringContainsString('font-family: "Evil \"Font\" \\\\ \3C /style\3E \A \7F ";', $css);
        self::assertStringContainsString('url("a\")\3C /style\3E \3C script\3E ") format("woff2")', $css);
    }

    public function testWritesTheRuleOfAFaceOfAFile(): void
    {
        $file = FontFile::fromBytes(FontBuilder::collection([
            ['name' => FontBuilder::names([1 => 'First'])],
            ['name' => FontBuilder::names([1 => 'Second'])],
        ]));

        self::assertStringContainsString("font-family: \"First\";\n    src: url(\"/c.ttc\") format(\"collection\");", FontFaceRule::forFile($file, '/c.ttc')->toCss());
        self::assertStringContainsString("font-family: \"Second\";\n    src: url(\"/c.ttc\") format(\"collection\");", FontFaceRule::forFile($file, '/c.ttc', 1)->toCss());
    }

    public function testRefusesARuleWithoutFamily(): void
    {
        $this->expectException(InvalidFontFace::class);
        $this->expectExceptionMessageIsOrContains('An @font-face rule needs a family; the font names none, so give one with withFamily().');

        FontFaceRule::for(new Font())->withUrl('a.ttf', Format::TrueType)->toCss();
    }

    public function testRefusesARuleWithoutSource(): void
    {
        $this->expectException(InvalidFontFace::class);
        $this->expectExceptionMessageIsOrContains('An @font-face rule needs a source: a URL, or the local names of the font.');

        FontFaceRule::for(new Font(names: $this->names([1 => 'Lonely', 6 => 'Lonely'])))->withoutLocal()->toCss();
    }

    public function testAFamilyGivenStandsForTheOneMissing(): void
    {
        self::assertStringContainsString('font-family: "Given";', FontFaceRule::for(new Font())->withFamily('Given')->withUrl('a.ttf', Format::TrueType)->toCss());
    }
}
