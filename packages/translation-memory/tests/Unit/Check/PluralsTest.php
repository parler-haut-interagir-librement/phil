<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Check;

use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\CheckIssue;
use Phil\TranslationMemory\Check\IcuPlurals;
use Phil\TranslationMemory\Check\Plurals;
use Phil\TranslationMemory\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Plurals::class)]
#[CoversClass(IcuPlurals::class)]
final class PluralsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, list<string>}>, bool}>
     */
    public static function messages(): iterable
    {
        yield 'plain text' => ['No argument here.', [], true];
        yield 'simple argument' => ['Hello {name}!', [], true];
        yield 'formatted argument' => ['On {day, date, short} at {n, number, ::currency/CAD}', [], true];
        yield 'plural' => ['{count, plural, one {# point} other {# points}}', [['count', ['one', 'other']]], true];
        yield 'offset and explicit' => ['{n, plural, offset:1 =0 {nobody} one {you} other {you and # others}}', [['n', ['=0', 'one', 'other']]], true];
        yield 'nested in select' => ['{g, select, female {{n, plural, one {elle} other {elles}}} other {{n,plural,other{ils}}}}', [['n', ['one', 'other']], ['n', ['other']]], true];
        yield 'quoted braces' => ["'{'literal'}' {n, plural, other {x '}' y}}", [['n', ['other']]], true];
        yield 'doubled apostrophe' => ["l''{n, plural, other {x}}", [['n', ['other']]], true];
        yield 'apostrophe in a word' => ["l'épée {n, plural, other {x}}", [['n', ['other']]], true];
        yield 'selectordinal not a plural' => ['{n, selectordinal, one {#st} other {#th}}', [], true];
        yield 'unclosed' => ['{count, plural, one {# point} other {# points}', [], false];
        yield 'stray closing' => ['a } b', [], false];
        yield 'empty name' => ['{}', [], false];
        yield 'empty name with type' => ['{, number}', [], false];
        yield 'unclosed name' => ['{name', [], false];
        yield 'unclosed type' => ['{name, number', [], false];
        yield 'unclosed style' => ['{name, number, integer', [], false];
        yield 'no selectors' => ['{n, plural, }', [], false];
        yield 'selector without message' => ['{n, plural, one}', [], false];
        yield 'unclosed option' => ['{n, plural, one {x', [], false];
        yield 'unclosed options' => ['{n, plural, one {x}', [], false];
        yield 'padded argument' => ['{ n , plural , one {x} other {y} }', [['n', ['one', 'other']]], true];
        yield 'type without style then a stray brace' => ['{n, number}}', [], false];
        yield 'name only then a stray brace' => ['{n}}', [], false];
        yield 'selector at the end' => ['{n, plural, one', [], false];
        yield 'selector then spaces at the end' => ['{n, plural, one ', [], false];
        yield 'empty selector' => ['{n, plural, {x}}', [], false];
        yield 'selector then text' => ['{n, plural, one x}', [], false];
        yield 'quoted braces then an argument' => ["'{x}' {n, plural, other {y}}", [['n', ['other']]], true];
        yield 'quoted braces right before an argument' => ["'{x}'{n, plural, other {y}}", [['n', ['other']]], true];
        yield 'quote then one character then an argument' => ["'a{n, plural, other {y}}", [['n', ['other']]], true];
        yield 'bad nested argument' => ['{n, plural, one {{}} other {x}}', [], false];
        yield 'unclosed quote' => ["'{ never closed", [], true];
    }

    /**
     * @param list<array{string, list<string>}> $plurals
     */
    #[DataProvider('messages')]
    public function testReadsThePluralsOfAMessage(string $message, array $plurals, bool $balanced): void
    {
        self::assertSame([$plurals, $balanced], IcuPlurals::read($message));
    }

    public function testNestsSixteenArgumentsDeepAtMost(): void
    {
        $nested = static fn (int $depth): string => str_repeat('{a, select, other {', $depth - 1).'{z}'.str_repeat('}}', $depth - 1);

        self::assertTrue(IcuPlurals::read($nested(16))[1]);
        self::assertFalse(IcuPlurals::read($nested(17))[1]);
    }

    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function targets(): iterable
    {
        $source = '{count, plural, one {# point} other {# points}}';

        yield 'French' => [$source, '{count, plural, one {# point} other {# points}}', 'fr-CA', []];
        yield 'explicit cases welcome' => [$source, '{count, plural, =0 {aucun} one {# point} other {# points}}', 'fr', []];
        yield 'Polish' => [$source, '{count, plural, one {# punkt} few {# punkty} many {# punktów} other {# punktu}}', 'pl', []];
        yield 'Polish lacking' => [$source, '{count, plural, one {# punkt} other {# punktu}}', 'pl', ['The plural of “count” lacks the forms few, many.']];
        yield 'Japanese' => [$source, '{count, plural, other {#ポイント}}', 'ja', []];
        yield 'unknown language' => [$source, '{count, plural, other {#}}', 'tlh', []];
        yield 'French lacking one' => [$source, '{count, plural, other {# points}}', 'fr', ['The plural of “count” lacks the forms one.']];
        yield 'renamed' => [$source, '{n, plural, one {# point} other {# points}}', 'fr', ['The target has no plural for “count”.']];
        yield 'not a plural' => [$source, '{count} points', 'fr', ['The target has no plural for “count”.']];
        yield 'unbalanced' => [$source, '{count, plural, one {# point} other {# points}', 'fr', ['The braces of the target do not balance.']];
        yield 'split in two' => ['{n, plural, one {a} other {b}} {n, plural, one {c} other {d}}', '{n, plural, one {a} other {b}} {n, plural, other {d}}', 'fr', []];
        yield 'twice the same name' => ['{n, plural, one {a} other {b}} {n, plural, one {c} other {d}}', 'Bonjour', 'fr', ['The target has no plural for “n”.']];
        yield 'two names, the first missing' => ['{a, plural, other {x}} {b, plural, other {y}}', '{b, plural, other {y}}', 'fr', ['The target has no plural for “a”.', 'The plural of “b” lacks the forms one.']];
        yield 'no plural in the source, an unbalanced target' => ['Hello {name}', 'Bonjour {', 'fr', []];
        yield 'no plural in the source' => ['Hello {name}', 'Bonjour', 'fr', []];
        yield 'no brace in the source' => ['Hello', '{', 'fr', []];
        yield 'entities decoded' => ['{n, plural, one {&lt;b&gt;} other {x}}', '{n, plural, one {a} other {b}}', 'fr', []];
    }

    /**
     * @param list<string> $issues
     */
    #[DataProvider('targets')]
    public function testChecksTheFormsOfThePluralsOfTheSource(string $source, string $target, string $language, array $issues): void
    {
        $found = new Plurals()->check(new Checked($source, $target, LanguageTag::of('en'), LanguageTag::of($language)));

        self::assertSame($issues, array_map(static fn (CheckIssue $issue): string => $issue->message, $found));
        self::assertSame([] === $issues ? [] : ['plurals'], array_values(array_unique(array_map(static fn (CheckIssue $issue): string => $issue->check, $found))));
    }

    public function testKnowsTheCategoriesOfWholeNumbers(): void
    {
        self::assertSame('plurals', new Plurals()->name());
        self::assertSame(['one', 'other'], Plurals::categories(LanguageTag::of('fr-CA')));
        self::assertSame(['one', 'few', 'other'], Plurals::categories(LanguageTag::of('cs')));
        self::assertSame(['one', 'few', 'many', 'other'], Plurals::categories(LanguageTag::of('ru')));
        self::assertSame(['one', 'two', 'other'], Plurals::categories(LanguageTag::of('he')));
        self::assertSame(['one', 'two', 'few', 'many', 'other'], Plurals::categories(LanguageTag::of('ga')));
        self::assertSame(['zero', 'one', 'two', 'few', 'many', 'other'], Plurals::categories(LanguageTag::of('ar')));
        self::assertSame(['other'], Plurals::categories(LanguageTag::of('zh-Hant')));
    }
}
