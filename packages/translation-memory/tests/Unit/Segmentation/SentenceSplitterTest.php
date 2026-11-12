<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Segmentation;

use Phil\TranslationMemory\Segmentation\SentenceSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SentenceSplitter::class)]
final class SentenceSplitterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function texts(): iterable
    {
        yield 'empty' => ['', []];
        yield 'one sentence' => ['Hello world', ['Hello world']];
        yield 'three ends' => ['One. Two! Three? Four… Five', ['One. ', 'Two! ', 'Three? ', 'Four… ', 'Five']];
        yield 'white space kept' => ["One.  \n Two.", ["One.  \n ", 'Two.']];
        yield 'trailing white space' => ['One. Two. ', ['One. ', 'Two. ']];
        yield 'no space after' => ['Version 3.5 is out. Yes', ['Version 3.5 is out. ', 'Yes']];
        yield 'lower case after' => ['Wait... and see. Done', ['Wait... and see. ', 'Done']];
        yield 'several marks' => ['Really?! Yes.', ['Really?! ', 'Yes.']];
        yield 'closing quote' => ['He said "go." Then left.', ['He said "go." ', 'Then left.']];
        yield 'closing bracket' => ['(See below.) Next', ['(See below.) ', 'Next']];
        yield 'closing guillemet' => ['« Oui. » Puis', ['« Oui. » ', 'Puis']];
        yield 'closing tag' => ['I am <b>fine.</b> You?', ['I am <b>fine.</b> ', 'You?']];
        yield 'opening tag after' => ['One. <i>Two</i>', ['One. ', '<i>Two</i>']];
        yield 'lower case after a tag' => ['One. <i>two</i>', ['One. <i>two</i>']];
        yield 'tag with a period' => ['<a href="a.b. C">x</a> y', ['<a href="a.b. C">x</a> y']];
        yield 'abbreviation' => ['Ask Dr. Smith. Now', ['Ask Dr. Smith. ', 'Now']];
        yield 'abbreviation in capitals' => ['Ask MR. Smith', ['Ask MR. Smith']];
        yield 'abbreviation with periods' => ['Fruit, e.g. Apples', ['Fruit, e.g. Apples']];
        yield 'abbreviation after a quote' => ['("Mr. Smith")', ['("Mr. Smith")']];
        yield 'abbreviation after a tag' => ['<b>Mr. Smith</b>', ['<b>Mr. Smith</b>']];
        yield 'initials' => ['J. R. R. Tolkien wrote. Yes', ['J. R. R. Tolkien wrote. ', 'Yes']];
        yield 'accented initial' => ['É. Zola. Yes', ['É. Zola. ', 'Yes']];
        yield 'not an initial' => ['Item 7. Next', ['Item 7. ', 'Next']];
        yield 'two letters' => ['Go up. No', ['Go up. ', 'No']];
        yield 'wide end' => ['日本。東京！大阪？', ['日本。', '東京！', '大阪？']];
        yield 'wide end with closer' => ['「日本。」次', ['「日本。」', '次']];
        yield 'wide end at the end' => ['日本。', ['日本。']];
        yield 'digit after' => ['Chapter one. 2 more', ['Chapter one. ', '2 more']];
        yield 'unclosed tag' => ['One. <b', ['One. <b']];
        yield 'end then tag only' => ['One. <br>', ['One. <br>']];
        yield 'abbreviation in other capitals' => ['I.E. Here. C.-À-D. Là', ['I.E. Here. ', 'C.-À-D. Là']];
        yield 'accented lower case after' => ['One. école', ['One. école']];
        yield 'no-break space' => ["One.\u{00A0}Two", ["One.\u{00A0}", 'Two']];
        yield 'slash after closers' => ['End.)/ <b> Next', ['End.)/ <b> Next']];
    }

    /**
     * @param list<string> $sentences
     */
    #[DataProvider('texts')]
    public function testCutsATextIntoSentencesThatGiveItBack(string $text, array $sentences): void
    {
        $split = new SentenceSplitter()->split($text);

        self::assertSame($sentences, $split);
        self::assertSame($text, implode('', $split));
    }

    public function testTakesItsOwnAbbreviations(): void
    {
        $splitter = new SentenceSplitter(['Approx']);

        self::assertSame(['Approx. Ten'], $splitter->split('Approx. Ten'));
        self::assertSame(['Ask Dr. ', 'Smith'], $splitter->split('Ask Dr. Smith'));
        self::assertSame(['Été. Chaud'], new SentenceSplitter(['ÉTÉ'])->split('Été. Chaud'));
    }
}
