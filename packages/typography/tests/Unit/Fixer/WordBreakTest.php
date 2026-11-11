<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\WordBreak;
use Phil\Typography\Text\Run;
use Phil\Typography\Typographer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordBreak::class)]
final class WordBreakTest extends TestCase
{
    private const string ZWSP = "\u{200B}";

    public function testThaiWordsAreSeparated(): void
    {
        $fixer = new WordBreak();

        self::assertSame(['สวัสดี'.self::ZWSP.'ครับ'], $fixer->fix(Run::of('สวัสดีครับ'))->texts);
        self::assertSame(['ภาษา'.self::ZWSP.'ไทย'], $fixer->fix(Run::of('ภาษาไทย'))->texts);
        self::assertSame('th', WordBreak::LANGUAGE);
    }

    public function testNeverBeforeTheRepetitionMark(): void
    {
        self::assertSame(['เด็กๆ'.self::ZWSP.'เล่น'.self::ZWSP.'กัน'], new WordBreak()->fix(Run::of('เด็กๆเล่นกัน'))->texts);
    }

    public function testOnlyThaiIsRead(): void
    {
        $fixer = new WordBreak();

        self::assertSame(['Hello สวัสดี'.self::ZWSP.'ครับ, 2026 😀 ภาษา'.self::ZWSP.'ไทย!'], $fixer->fix(Run::of('Hello สวัสดีครับ, 2026 😀 ภาษาไทย!'))->texts);
        self::assertSame(['Hello world'], $fixer->fix(Run::of('Hello world'))->texts);
        self::assertSame(['ภาษา', 'LOCKภาษาไทย', 'ไทย'], $fixer->fix(new Run(['ภาษา', 'LOCKภาษาไทย', 'ไทย'], [false, true]))->texts);
    }

    public function testFixingTwiceGivesTheSameText(): void
    {
        $fixer = new WordBreak();
        $once = $fixer->fix(Run::of('สวัสดีครับ ภาษาไทย'));

        self::assertSame($once->texts, $fixer->fix($once)->texts);
    }

    public function testThePresetOfThai(): void
    {
        self::assertSame('<p>สวัสดี'.self::ZWSP.'ครับ</p>', Typographer::for('th_TH')->fix('<p>สวัสดีครับ</p>'));
        self::assertSame('<p>สวัสดีครับ</p>', Typographer::for('th_TH', false)->fix('<p>สวัสดีครับ</p>'));
    }
}
