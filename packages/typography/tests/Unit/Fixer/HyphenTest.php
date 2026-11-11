<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\Hyphen;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Hyphenation\Patterns;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hyphen::class)]
final class HyphenTest extends TestCase
{
    private const string SHY = "\u{AD}";

    public function testLongWordsTakeSoftHyphens(): void
    {
        $fixer = new Hyphen(Hyphenator::for('en_US'));

        self::assertSame(['hy'.self::SHY.'phen'.self::SHY.'ation is ex'.self::SHY.'tra'.self::SHY.'or'.self::SHY.'di'.self::SHY.'nary'], $fixer->fix(Run::of('hyphenation is extraordinary'))->texts);
        self::assertSame(5, Hyphen::MIN_LENGTH);
    }

    public function testTheShortestWordHyphenated(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('1a1 1b1 1c1 1d1 1e1 1é1', '', 1, 1));

        self::assertSame(['a'.self::SHY.'b'.self::SHY.'c'.self::SHY.'d'.self::SHY.'e abcd'], new Hyphen($hyphenator)->fix(Run::of('abcde abcd'))->texts);
        self::assertSame(['a'.self::SHY.'b'.self::SHY.'c abcd'.self::SHY.'e'], new Hyphen($hyphenator, 3)->fix(Run::of('abc abcd'.self::SHY.'e'))->texts);
        self::assertSame(['abcdé'], new Hyphen($hyphenator, 6)->fix(Run::of('abcdé'))->texts);
        self::assertSame(["e\u{301}".self::SHY.'a'], new Hyphen($hyphenator, 2)->fix(Run::of("e\u{301}a"))->texts);
    }

    public function testWordsAreLettersOnly(): void
    {
        $hyphenator = new Hyphenator(Patterns::parse('1a1 1b1', '', 1, 1));
        $fixer = new Hyphen($hyphenator, 2);

        self::assertSame(['a'.self::SHY.'b1a'.self::SHY.'b 😀a'.self::SHY.'b'], $fixer->fix(Run::of('ab1ab 😀ab'))->texts);
        self::assertSame(['a'.self::SHY.'b', 'LOCK', 'a'.self::SHY.'b'], $fixer->fix(new Run(['ab', 'LOCK', 'ab'], [false, true]))->texts);
    }
}
