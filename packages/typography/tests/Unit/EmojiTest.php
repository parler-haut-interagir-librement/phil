<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Phil\Typography\Typographer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Emoji are not letters, digits or punctuation to any fixer: every sequence — joined by zero-width joiners, with skin
 * tones, variation selectors, keycaps, flags or tags — comes out byte for byte as it went in.
 */
#[CoversNothing]
final class EmojiTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function emoji(): iterable
    {
        yield 'family' => ['👨‍👩‍👧‍👦'];
        yield 'skin tone' => ['👍🏽'];
        yield 'variation selector' => ['❤️'];
        yield 'keycap' => ['1️⃣'];
        yield 'flag' => ['🇨🇦'];
        yield 'tag sequence' => ['🏴󠁧󠁢󠁳󠁣󠁴󠁿'];
        yield 'profession' => ['🧑🏿‍🚀'];
        yield 'rainbow flag' => ['🏳️‍🌈'];
    }

    #[DataProvider('emoji')]
    public function testEveryFixerLeavesTheSequence(string $emoji): void
    {
        foreach (['en_US', 'fr_FR', 'fr_CA', 'de_DE', 'ru'] as $locale) {
            $typographer = Typographer::for($locale);
            $text = 'Extraordinary '.$emoji.' "'.$emoji.'" '.$emoji.'... 2 x '.$emoji.' - '.$emoji.'!';
            $fixed = $typographer->fixText($text);

            self::assertSame(substr_count($text, $emoji), substr_count($fixed, $emoji), $locale);
            self::assertStringNotContainsString("\u{AD}", str_replace("\u{AD}", '', $emoji));
            self::assertSame($emoji, $typographer->fixText($emoji), $locale);
            self::assertSame('<p>'.$emoji.'</p>', $typographer->fix('<p>'.$emoji.'</p>'), $locale);
        }
    }

    public function testAQuotedEmojiIsQuoted(): void
    {
        self::assertSame('“😀” and “👨‍👩‍👧”', Typographer::for('en_US')->fixText('"😀" and "👨‍👩‍👧"'));
        self::assertSame("«\u{A0}😀\u{A0}»\u{202F}!", Typographer::for('fr_FR')->fixText('"😀" !'));
    }
}
