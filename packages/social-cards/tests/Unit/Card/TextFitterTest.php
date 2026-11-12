<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\FittedText;
use Phil\SocialCards\Card\LineBreaker;
use Phil\SocialCards\Card\TextFitter;
use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\Exception\InvalidShareMetadata;
use Phil\SocialCards\Tests\Support\MonospaceMeasure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextFitter::class)]
#[CoversClass(LineBreaker::class)]
#[CoversClass(FittedText::class)]
#[CoversClass(CardContent::class)]
final class TextFitterTest extends TestCase
{
    private CardFont $font;

    protected function setUp(): void
    {
        $this->font = CardFont::fromFile(__DIR__.'/../../Fixtures/card-font.ttf');
    }

    public function testWhereLinesBreakByLanguage(): void
    {
        self::assertSame(['Hello, ', 'world-', 'wide ', 'web'], LineBreaker::segments('Hello, world-wide web', 'en'));
        self::assertSame(['日', '本', '語', 'の', 'テ', 'キ', 'ス', 'ト'], LineBreaker::segments('日本語のテキスト', 'ja'));
        self::assertSame(['Le ', 'jeu : ', 'une ', '« aventure » ! ', 'Vraiment ?'], LineBreaker::segments('Le jeu : une « aventure » ! Vraiment ?', 'fr_CA'));
        self::assertSame(['Le ', 'jeu : ', 'oui ; ', '«non»'], LineBreaker::segments("Le jeu\u{202F}: oui ; «non»", 'FR'));
        self::assertSame(['a b ', 'c'], LineBreaker::segments("a\u{00A0}b c", 'en'), 'A no-break space glues and is drawn as a space.');
        self::assertSame([], LineBreaker::segments('', 'en'));
        self::assertSame(['x'], LineBreaker::segments('x', 'xx_unknown'));
    }

    public function testTheLargestSizeThatHolds(): void
    {
        $measure = new MonospaceMeasure();
        $fitter = new TextFitter($measure);

        // 10 characters at 20 px are 100 px: one line.
        self::assertEquals(new FittedText(['abcde fghi'], 20), $fitter->fit('abcde fghi', 'en', $this->font, 100, 1, 20, 10));
        // 11 characters need 18 px at most on one line: the halving finds it.
        self::assertEquals(new FittedText(['abcde fghij'], 18), $fitter->fit('abcde fghij', 'en', $this->font, 100, 1, 20, 10));
        // On two lines, the largest size holds.
        self::assertEquals(new FittedText(['abcde', 'fghij'], 40), $fitter->fit('abcde fghij', 'en', $this->font, 100, 2, 40, 10));
        // Between two sizes, the larger one that holds: 12 characters on one line hold at 16 px, not 17.
        self::assertEquals(new FittedText(['abcdef ghijk'], 16), $fitter->fit('abcdef ghijk', 'en', $this->font, 100, 1, 30, 10));
        self::assertEquals(new FittedText(['abcdef ghijk'], 16), $fitter->fit('abcdef ghijk', 'en', $this->font, 100, 1, 17, 10));
        self::assertEquals(new FittedText(['abcdef ghijk'], 16), $fitter->fit('abcdef ghijk', 'en', $this->font, 100, 1, 17, 16));
    }

    public function testTheHalvingFindsWhatALinearSearchFinds(): void
    {
        $measure = new MonospaceMeasure();
        $fitter = new TextFitter($measure);
        $words = ['a', 'bb', 'ccc', 'dddd', 'eeeee', 'ffffff', 'ggggggg', 'hhhhhhhh'];

        for ($length = 1; $length <= 60; $length += 3) {
            $text = '';

            for ($index = 0; mb_strlen($text) < $length; ++$index) {
                $text .= $words[($index * 5 + $length) % 8].' ';
            }

            foreach ([[1, 64, 8], [2, 64, 36], [3, 75, 8], [2, 41, 40]] as [$lines, $max, $min]) {
                $expected = null;

                for ($size = $max; $size >= $min && !$expected instanceof FittedText; --$size) {
                    $wrapped = $this->greedy(trim($text), $size, 300);
                    $expected = \count($wrapped) <= $lines ? new FittedText($wrapped, $size) : null;
                }

                if ($expected instanceof FittedText) {
                    self::assertEquals($expected, $fitter->fit($text, 'en', $this->font, 300, $lines, $max, $min), \sprintf('"%s" in %d lines, %d to %d px.', $text, $lines, $max, $min));
                }
            }
        }

        $measure->calls = 0;
        self::assertSame(50, $fitter->fit('abcdef ghijk', 'en', $this->font, 300, 1, 200, 8)->size);
        self::assertSame(41, $measure->calls, 'Each size tried measures the text afresh: both bounds, then the halving of 8 to 200.');
    }

    public function testAWordWiderThanTheBoxIsCut(): void
    {
        $fitter = new TextFitter(new MonospaceMeasure());

        self::assertEquals(new FittedText(['ab', 'abcdefghij', 'klmnopqrst', 'uv cd'], 20), $fitter->fit('ab abcdefghijklmnopqrstuv cd', 'en', $this->font, 100, 4, 20, 20));
        self::assertEquals(new FittedText(['abcdefghij', 'kl'], 20), $fitter->fit('abcdefghijkl', 'en', $this->font, 100, 2, 20, 20));
    }

    public function testAtTheSmallestSizeTheEndIsCut(): void
    {
        $measure = new MonospaceMeasure();
        $fitter = new TextFitter($measure);

        self::assertEquals(new FittedText(['abcd efgh', 'ijkl mnop…'], 20, true), $fitter->fit('abcd efgh ijkl mnop qrst uvwx', 'en', $this->font, 100, 2, 40, 20));
        self::assertEquals(new FittedText(['abcdefghi…'], 20, true), $fitter->fit('abcdefghijkl', 'en', $this->font, 100, 1, 20, 20), 'The last line loses characters until the ellipsis holds.');
        self::assertEquals(new FittedText(['…'], 20, true), $fitter->fit('abcdefghijk', 'en', $this->font, 5, 1, 20, 20), 'Nothing holds beside the ellipsis.');
        self::assertEquals(new FittedText(['abcd…'], 20, true), $fitter->fit('abcd e fg', 'en', $this->font, 60, 1, 20, 20), 'The blank before the ellipsis is removed.');

        $measure->calls = 0;
        $fitter->fit(str_repeat('word ', 60), 'en', $this->font, 100, 3, 64, 36);
        self::assertLessThan(60, $measure->calls, 'A text far too long stops wrapping after one line more than the box holds.');
    }

    public function testNoLineAndTrailingBlanks(): void
    {
        $measure = new MonospaceMeasure();
        $fitter = new TextFitter($measure);

        self::assertEquals(new FittedText([], 40, true), $fitter->fit('abc', 'en', $this->font, 100, 0, 40, 20));
        self::assertSame(0, $measure->calls);
        self::assertEquals(new FittedText(['abc'], 20), $fitter->fit('abc  ', 'en', $this->font, 100, 1, 20, 20));
        self::assertEquals(new FittedText([], 20), $fitter->fit('', 'en', $this->font, 100, 1, 20, 20));
    }

    public function testTheHeightOfTheLines(): void
    {
        self::assertSame([0, 25, 80, 63, 61], [new FittedText([], 20)->height(), new FittedText(['a'], 20)->height(), new FittedText(['a', 'b'], 32)->height(), FittedText::lineHeight(50), FittedText::lineHeight(49)]);
        self::assertFalse(new FittedText([], 1)->truncated);
    }

    public function testTheContentOfACard(): void
    {
        $content = new CardContent("  A\ttitle ", ' ', 'fr-ca');

        self::assertSame(['title' => 'A title', 'subtitle' => null, 'locale' => 'fr_CA'], $content->toArray());
        self::assertSame(['en', 'Sub'], [new CardContent('T', 'Sub')->locale, new CardContent('T', 'Sub')->subtitle]);
        self::assertSame(300, mb_strlen(new CardContent(str_repeat('a', 400), str_repeat('b', 400))->title));
        self::assertSame(300, mb_strlen((string) new CardContent('T', str_repeat('b', 400))->subtitle));

        try {
            new CardContent('T', null, 'nope!');
            self::fail('The locale is refused.');
        } catch (InvalidShareMetadata) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidCardTemplate::class);
        $this->expectExceptionMessageIsOrContains('A card needs a title.');
        new CardContent(' ');
    }

    /**
     * The lines of a text by a greedy wrap at a size, the reference the fitter must agree with.
     *
     * @return list<string>
     */
    private function greedy(string $text, int $size, int $width): array
    {
        $lines = [''];

        foreach (explode(' ', $text) as $word) {
            $last = \count($lines) - 1;
            $candidate = '' === $lines[$last] ? $word : $lines[$last].' '.$word;

            if ('' === $lines[$last] || intdiv(mb_strlen($candidate) * $size, 2) <= $width) {
                $lines[$last] = $candidate;
            } else {
                $lines[] = $word;
            }
        }

        return $lines;
    }
}
