<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Xliff;

use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Xliff\InlineCodes;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use Phil\TranslationMemory\Xliff\XliffWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XliffWriter::class)]
#[CoversClass(XliffFile::class)]
#[CoversClass(XliffUnit::class)]
#[CoversClass(InlineCodes::class)]
final class XliffWriterTest extends TestCase
{
    public function testWritesOneUnitPerSegment(): void
    {
        $segments = [
            new Segment('b1/text', 'Hello'),
            new Segment('b2/text', 'Tom &amp; Jerry')->suggest('Tom et Jerry'),
            new Segment('b3/text', 'Yes')->translate('Oui'),
            new Segment('b4/text', 'No')->translate('Non')->review(),
            new Segment('b5/text', 'Old')->translate('Vieux')->withSource('New &lt;one&gt;'),
        ];

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en" trgLang="fr-CA"><file id="f1" original="page:1">'
            .'<unit id="u1" name="b1/text"><segment state="initial"><source>Hello</source></segment></unit>'
            .'<unit id="u2" name="b2/text"><segment state="initial" subState="phil:suggested"><source>Tom &amp; Jerry</source><target>Tom et Jerry</target></segment></unit>'
            .'<unit id="u3" name="b3/text"><segment state="translated"><source>Yes</source><target>Oui</target></segment></unit>'
            .'<unit id="u4" name="b4/text"><segment state="reviewed"><source>No</source><target>Non</target></segment></unit>'
            .'<unit id="u5" name="b5/text"><notes><note category="phil:previous-source">Old</note></notes><segment state="initial" subState="phil:stale"><source>New &lt;one&gt;</source><target>Vieux</target></segment></unit>'
            .'</file></xliff>'."\n",
            new XliffWriter()->write(XliffFile::fromSegments('page:1', LanguageTag::of('en'), LanguageTag::of('fr-ca'), $segments)),
        );
    }

    public function testWritesTagsAsCodesSharedByTheSourceAndTheTarget(): void
    {
        $segment = new Segment('k', 'A <b>bold</b> and <b>more</b> <br>')->translate('<i>X</i> <b>gras</b> <a href="#">y</a>');
        $xml = new XliffWriter()->write(XliffFile::fromSegments('d', LanguageTag::of('en'), LanguageTag::of('fr'), [$segment]));

        self::assertStringContainsString(
            '<originalData><data id="d1">&lt;b&gt;</data><data id="d2">&lt;/b&gt;</data><data id="d3">&lt;b&gt;</data><data id="d4">&lt;/b&gt;</data><data id="d5">&lt;br&gt;</data>'
            .'<data id="d6">&lt;i&gt;</data><data id="d7">&lt;/i&gt;</data><data id="d8">&lt;a href="#"&gt;</data><data id="d9">&lt;/a&gt;</data></originalData>',
            $xml,
        );
        self::assertStringContainsString('<source>A <ph id="1" dataRef="d1"/>bold<ph id="2" dataRef="d2"/> and <ph id="3" dataRef="d3"/>more<ph id="4" dataRef="d4"/> <ph id="5" dataRef="d5"/></source>', $xml);
        self::assertStringContainsString('<target><ph id="6" dataRef="d6"/>X<ph id="7" dataRef="d7"/> <ph id="1" dataRef="d1"/>gras<ph id="2" dataRef="d2"/> <ph id="8" dataRef="d8"/>y<ph id="9" dataRef="d9"/></target>', $xml);

        $unit = new XliffReader()->read($xml)->units[0];
        self::assertSame(['A <b>bold</b> and <b>more</b> <br>', '<i>X</i> <b>gras</b> <a href="#">y</a>'], [$unit->source, $unit->target]);
    }

    public function testWritesAFileWithoutTargetLanguage(): void
    {
        $xml = new XliffWriter()->write(new XliffFile(LanguageTag::of('en'), null, 'd', [new XliffUnit('k', 'Hi')]));

        self::assertStringContainsString('<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en"><file', $xml);
    }

    public function testCutsInlineHtmlIntoWordsAndTags(): void
    {
        self::assertSame([[false, 'a '], [true, '<b>'], [false, 'b'], [true, '</b>']], InlineCodes::tokens('a <b>b</b>'));
        self::assertSame([[false, 'a <3 b']], InlineCodes::tokens('a <3 b'));
        self::assertSame([[false, 'a > b']], InlineCodes::tokens('a > b'));
        self::assertSame([[true, '<b>'], [true, '</b>']], InlineCodes::tokens('<b></b>'));
        self::assertSame([], InlineCodes::tokens(''));
        self::assertSame([], InlineCodes::tokens("\xFF<b>"));
    }

    public function testKeepsOnlyTheTagsOfTheSource(): void
    {
        self::assertSame('<b>x</b> y z', InlineCodes::restrictTo('<b>x</b> <script>y</script> <a href="evil">z</a>', 'A <b>b</b>'));
        self::assertSame('x', InlineCodes::restrictTo('<i>x</i>', 'plain'));
    }

    public function testDecodesAndEncodesWords(): void
    {
        self::assertSame('Tom & "Jerry" ’', InlineCodes::decode('Tom &amp; &quot;Jerry&quot; &rsquo;'));
        self::assertSame('a &lt;b&gt; &amp; &quot;c&quot; &apos;d&apos;', InlineCodes::encode('a <b> & "c" \'d\''));
        self::assertSame("\u{FFFD}", InlineCodes::encode("\xFF"));
    }
}
