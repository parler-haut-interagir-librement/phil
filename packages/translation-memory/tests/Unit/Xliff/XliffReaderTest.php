<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Xliff;

use Phil\TranslationMemory\Exception\InvalidXliff;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(XliffReader::class)]
#[CoversClass(InvalidXliff::class)]
final class XliffReaderTest extends TestCase
{
    public function testReadsXliff2(): void
    {
        $file = new XliffReader()->read(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="fr-ca">
              <file id="f1" original="page:1">
                <unit id="u1" name="b1/text"><segment state="translated"><source>Hello</source><target>Bonjour</target></segment></unit>
                <group id="g1">
                  <unit id="u2"><segment><source>Yes</source><target>Oui</target></segment></unit>
                </group>
                <unit id="u3" name="b3/text"><segment state="final"><source>No</source><target>Non</target></segment></unit>
                <unit id="u4" name="b4/text"><segment state="reviewed"><source>Up</source><target>Haut</target></segment></unit>
                <unit id="u5" name="b5/text"><segment state="translated"><source>Down</source></segment></unit>
                <unit id="u6" name="b6/text"><segment state="translated"><source>Left</source><target> </target></segment></unit>
                <unit id="u7" name="b7/text"><notes><note>n</note></notes></unit>
              </file>
              <file id="f2" original="other"><unit id="u8"><segment><source>More</source></segment></unit></file>
            </xliff>
            XML);

        self::assertSame(['en', 'fr-CA', 'page:1'], [$file->sourceLanguage->value, $file->targetLanguage?->value, $file->original]);
        self::assertSame([
            ['b1/text', 'Hello', 'Bonjour', 'translated'],
            ['u2', 'Yes', 'Oui', 'suggested'],
            ['b3/text', 'No', 'Non', 'reviewed'],
            ['b4/text', 'Up', 'Haut', 'reviewed'],
            ['b5/text', 'Down', null, 'untranslated'],
            ['b6/text', 'Left', null, 'untranslated'],
            ['b7/text', '', null, 'untranslated'],
            ['u8', 'More', null, 'untranslated'],
        ], $this->describe($file->units));
    }

    public function testJoinsTheSegmentsOfAUnitWithTheLeastState(): void
    {
        $units = new XliffReader()->read($this->xliff2(<<<'XML'
            <unit id="u1">
              <segment state="reviewed"><source>One.</source><target>Un.</target></segment>
              <ignorable><source> </source></ignorable>
              <segment state="translated"><source>Two.</source><target>Deux.</target></segment>
              <ignorable><source>|</source><target>/</target></ignorable>
            </unit>
            <unit id="u2"><segment state="reviewed"><source>A.</source><target>Un.</target></segment><segment state="reviewed"><source>B.</source></segment></unit>
            <unit id="u3"><segment state="translated"><source>A.</source><target>Un.</target></segment><segment state="initial"><source>B.</source><target>Deux.</target></segment></unit>
            <unit id="u4"><segment state="reviewed"><source>A.</source><target>Un.</target></segment><segment state="final"><source>B.</source><target>Deux.</target></segment></unit>
            <unit id="u5"><ignorable><source>x</source></ignorable></unit>
            <unit id="u6"><segment state="translated"><target>Seul</target></segment></unit>
            XML))->units;

        self::assertSame([
            ['u1', 'One. Two.|', 'Un. Deux./', 'translated'],
            ['u2', 'A.B.', null, 'untranslated'],
            ['u3', 'A.B.', 'Un.Deux.', 'suggested'],
            ['u4', 'A.B.', 'Un.Deux.', 'reviewed'],
            ['u5', 'x', null, 'untranslated'],
            ['u6', '', 'Seul', 'translated'],
        ], $this->describe($units));
    }

    public function testBringsBackTheHtmlOfInlineCodes(): void
    {
        $units = new XliffReader()->read($this->xliff2(<<<'XML'
            <unit id="u1">
              <originalData><data id="d1">&lt;b&gt;</data><data id="d2">&lt;/b&gt;</data><data id="d3">&lt;br&gt;</data></originalData>
              <segment state="translated">
                <source><ph id="1" dataRef="d1"/>A<ph id="2" dataRef="d2"/> &amp; <pc id="3" dataRefStart="d1" dataRefEnd="d2">B</pc><sc id="4" dataRef="d1"/>C<ec startRef="4" dataRef="d2"/><ph id="5" dataRef="d3"/><ph id="6"/></source>
                <target><mrk id="m1" type="term">Terme</mrk><sm id="s1"/>x<em startRef="s1"/><cp hex="2665"/><cp hex="zz"/><cp hex="110000"/><pc id="7">D</pc></target>
              </segment>
            </unit>
            XML))->units;

        self::assertSame(['<b>A</b> &amp; <b>B</b><b>C</b><br>', 'Termex♥D'], [$units[0]->source, $units[0]->target]);
    }

    public function testReadsXliff12(): void
    {
        $file = new XliffReader()->read(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="de" original="page:2" datatype="html">
                <body>
                  <trans-unit id="1" resname="b1/text"><source>Hello</source><target>Hallo</target></trans-unit>
                  <trans-unit id="2"><source>Yes</source><target state="needs-review-translation">Ja</target></trans-unit>
                  <trans-unit id="3" approved="yes"><source>No</source><target state="new">Nein</target></trans-unit>
                  <trans-unit id="4"><source>Up</source><target state="final">Oben</target></trans-unit>
                  <trans-unit id="5"><source>Down</source><target state="signed-off">Unten</target></trans-unit>
                  <trans-unit id="6"><source>Left</source><target state="translated">Links</target></trans-unit>
                  <trans-unit id="7"><source>Right</source></trans-unit>
                  <trans-unit id="8"><source>Back</source><target state="translated"> </target></trans-unit>
                  <group><trans-unit id="9"><source><g id="1">Bold</g> <x id="2"/>&amp; <ph id="3">&lt;br&gt;</ph><bpt id="4">&lt;i&gt;</bpt>it<ept id="4">&lt;/i&gt;</ept> <mrk mtype="term">t</mrk><bx id="5"/><ex id="5"/><it id="6" pos="open">&lt;u&gt;</it></source><target state="x-custom">ok</target></trans-unit></group>
                  <trans-unit id="10"><target>Only</target></trans-unit>
                </body>
              </file>
              <file source-language="fr" original="other"><body><trans-unit id="11"><source>Plus</source></trans-unit></body></file>
            </xliff>
            XML);

        self::assertSame(['en', 'de', 'page:2'], [$file->sourceLanguage->value, $file->targetLanguage?->value, $file->original]);
        self::assertSame([
            ['b1/text', 'Hello', 'Hallo', 'translated'],
            ['2', 'Yes', 'Ja', 'suggested'],
            ['3', 'No', 'Nein', 'reviewed'],
            ['4', 'Up', 'Oben', 'reviewed'],
            ['5', 'Down', 'Unten', 'reviewed'],
            ['6', 'Left', 'Links', 'translated'],
            ['7', 'Right', null, 'untranslated'],
            ['8', 'Back', null, 'untranslated'],
            ['9', 'Bold &amp; <br><i>it</i> t<u>', 'ok', 'suggested'],
            ['10', '', 'Only', 'translated'],
            ['11', 'Plus', null, 'untranslated'],
        ], $this->describe($file->units));
    }

    public function testAFileWithoutTargetLanguageHasNone(): void
    {
        self::assertNull(new XliffReader()->read('<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en"/>')->targetLanguage);
        self::assertNull(new XliffReader()->read('<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"><file source-language="en" original="x"/></xliff>')->targetLanguage);
        self::assertSame('', new XliffReader()->read('<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.2" srcLang="en"/>')->original);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not XML' => ['<xliff', 'it is not well-formed XML.'];
        yield 'empty' => ['', 'it is not well-formed XML.'];
        yield 'document type' => ['<?xml version="1.0"?><!doctype xliff [<!ENTITY a "b">]><xliff/>', 'a document type declaration is not allowed.'];
        yield 'document type in UTF-16' => [mb_convert_encoding('<?xml version="1.0" encoding="UTF-16LE"?><!DOCTYPE xliff [<!ENTITY a "b">]><xliff/>', 'UTF-16LE', 'UTF-8'), 'a document type declaration is not allowed.'];
        yield 'other root' => ['<html xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield 'no namespace' => ['<xliff version="2.0" srcLang="en"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield 'version 3' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="3.0" srcLang="en"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield 'version 1.2 in 2.0 namespace' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="1.2" srcLang="en"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield 'version 2.0 in 1.2 namespace' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="2.0"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield '1.2 root element' => ['<file xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"/>', 'the root is not an XLIFF 1.2 or 2.x element.'];
        yield 'no source language' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0"/>', '"" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".'];
        yield 'bad target language' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="x_y"/>', '"x_y" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".'];
        yield '1.2 without file' => ['<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2"/>', 'an XLIFF 1.2 file holds at least one <file>.'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatItCannotRead(string $xml, string $reason): void
    {
        $this->expectException(InvalidXliff::class);
        $this->expectExceptionMessageIsOrContains('The XLIFF file cannot be read: '.$reason);

        new XliffReader()->read($xml);
    }

    public function testLeavesTheErrorHandlingOfLibxmlAsItWas(): void
    {
        $before = libxml_use_internal_errors(false);

        try {
            new XliffReader()->read('<xliff');
        } catch (InvalidXliff) {
        }

        self::assertFalse(libxml_use_internal_errors($before));
        self::assertFalse(libxml_get_last_error());
    }

    private function xliff2(string $units): string
    {
        return '<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.1" srcLang="en" trgLang="fr"><file id="f1">'.$units.'</file></xliff>';
    }

    /**
     * @param list<XliffUnit> $units
     *
     * @return list<array{string, string, ?string, string}>
     */
    private function describe(array $units): array
    {
        return array_map(static fn (XliffUnit $unit): array => [$unit->key, $unit->source, $unit->target, $unit->state->value], $units);
    }
}
