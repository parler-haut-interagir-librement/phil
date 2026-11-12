<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Tests\Unit\Domain;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Domain\ValueObject\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SynonymSet::class)]
#[CoversClass(SynonymList::class)]
#[CoversClass(Violation::class)]
#[CoversClass(InvalidSynonyms::class)]
final class SynonymSetTest extends TestCase
{
    public function testRulesKeepTheirPhrasesNormalizedAndOnce(): void
    {
        $equivalent = SynonymSet::equivalent([' JDR ', "jeu\tde  rôle", 'jdr']);
        self::assertSame([['jdr', 'jeu de rôle'], ['jdr', 'jeu de rôle'], true, ['jdr', 'jeu de rôle']], [$equivalent->from, $equivalent->to, $equivalent->equivalent, $equivalent->phrases()]);

        $oneWay = SynonymSet::oneWay(['Donjon', 'DJ'], ['donjon', 'Dungeon']);
        self::assertSame([['donjon', 'dj'], ['donjon', 'dungeon'], false, ['donjon', 'dj', 'dungeon']], [$oneWay->from, $oneWay->to, $oneWay->equivalent, $oneWay->phrases()]);
        self::assertSame(['a'], SynonymSet::oneWay(['a', 'b'], ['a'])->to);
        self::assertSame(['a b c d e f g h'], SynonymSet::oneWay(['a b c d e f g h'], ['x'])->from);
        self::assertSame([str_repeat('é', 100)], SynonymSet::oneWay([str_repeat('É', 100)], ['x'])->from);
        self::assertCount(64, SynonymSet::equivalent(array_map(static fn (int $n): string => 'w'.$n, range(1, 64)))->from);
        self::assertSame('tab', SynonymSet::equivalent(["tab\u{a0}", 'x'])->from[0], 'a no-break space is white space, not a control character');
        self::assertSame('x y', SynonymSet::normalize("\r\nX \n Y "));
        self::assertSame('?', SynonymSet::normalize("\xFF"));
    }

    public function testBadRulesAreRefused(): void
    {
        foreach ([
            [static fn (): SynonymSet => SynonymSet::equivalent(['jdr', 'JDR']), 'Equivalent synonyms are two phrases or more.'],
            [static fn (): SynonymSet => SynonymSet::oneWay([], ['x']), 'One-way synonyms are one phrase or more on each side.'],
            [static fn (): SynonymSet => SynonymSet::oneWay(['x'], []), 'One-way synonyms are one phrase or more on each side.'],
            [static fn (): SynonymSet => SynonymSet::oneWay(['a', 'b'], ['B', 'a']), 'One-way synonyms change something: the two sides are the same.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', ' ']), 'A synonym is 1 to 100 characters and 8 words at most; "" is not.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', str_repeat('b', 101)]), 'A synonym is 1 to 100 characters and 8 words at most; "'.str_repeat('b', 101).'" is not.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', 'a b c d e f g h i']), 'A synonym is 1 to 100 characters and 8 words at most; "a b c d e f g h i" is not.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', "b\x00"]), 'A synonym is text of valid UTF-8 without control characters.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', "b\x7F"]), 'A synonym is text of valid UTF-8 without control characters.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', "\x1Fb"]), 'A synonym is text of valid UTF-8 without control characters.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(['a', "b\xFF"]), 'A synonym is text of valid UTF-8 without control characters.'],
            [static fn (): SynonymSet => SynonymSet::equivalent(array_map(static fn (int $n): string => 'w'.$n, range(0, 64))), 'A rule holds 64 phrases at most; 65 given.'],
            [static fn (): SynonymList => SynonymList::of('français'), 'A language is a tag such as "fr" or "fr-CA"; "français" is not.'],
            [static fn (): SynonymList => SynonymList::of("fr\n"), "A language is a tag such as \"fr\" or \"fr-CA\"; \"fr\n\" is not."],
            [static fn (): SynonymList => SynonymList::of('fr', array_fill(0, 10_001, SynonymSet::equivalent(['a', 'b']))), 'A language holds 10000 rules at most; 10001 given.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidSynonyms $invalidSynonyms) {
                self::assertSame([$message, null], [$invalidSynonyms->getMessage(), $invalidSynonyms->fileLine]);
            }
        }

        $atLine = InvalidSynonyms::atLine(7, 'a phrase is empty.');
        self::assertSame(['Line 7: a phrase is empty.', 7], [$atLine->getMessage(), $atLine->fileLine]);
    }

    public function testAListHoldsTheRulesOfOneLanguage(): void
    {
        $set = SynonymSet::equivalent(['a', 'b']);
        $list = SynonymList::of('fr_CA', [$set]);

        self::assertSame(['fr', [$set]], [$list->language, $list->sets]);
        self::assertSame(['en', []], [SynonymList::of('EN')->language, SynonymList::of('EN')->sets]);
        self::assertSame('fil', SynonymList::language('fil-PH'));
        self::assertSame([$set, $set], $list->with($set)->sets);
        self::assertSame('fr', $list->with($set)->language);
        self::assertCount(10_000, SynonymList::of('fr', array_fill(0, 10_000, $set))->sets);

        $this->expectException(InvalidSynonyms::class);
        SynonymList::of('fr', array_fill(0, 10_000, $set))->with($set);
    }

    public function testAViolationNamesItsRule(): void
    {
        $violation = new Violation(2, Violation::CHAIN, 'Why.');

        self::assertSame([2, 'chain', 'Why.'], [$violation->set, $violation->code, $violation->message]);
        self::assertSame('overlap', Violation::OVERLAP);
    }
}
