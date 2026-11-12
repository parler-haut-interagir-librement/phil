<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Tmx;

use Phil\TranslationMemory\Exception\InvalidTmx;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Tmx\TmxReader;
use Phil\TranslationMemory\Tmx\TmxWriter;
use Phil\TranslationMemory\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TmxWriter::class)]
#[CoversClass(TmxReader::class)]
#[CoversClass(Xml::class)]
#[CoversClass(InvalidTmx::class)]
final class TmxTest extends TestCase
{
    public function testWritesUnitsAsTmx(): void
    {
        $xml = new TmxWriter()->write(LanguageTag::of('en'), [
            new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr-CA'), 'A <b>bold</b> <br>move &amp; more', 'Un coup <b>audacieux</b> <br>et plus', 'page:1'),
            new MemoryUnit(LanguageTag::of('de'), LanguageTag::of('fr'), 'Hallo', 'Bonjour'),
            new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<i>open <b>x</i> end</b>', 'Fin'),
        ]);

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <tmx version="1.4">
              <header creationtool="phil/translation-memory" creationtoolversion="0.1" segtype="sentence" o-tmf="phil" adminlang="en" srclang="en" datatype="html"/>
              <body>
                <tu>
                  <prop type="x-origin">page:1</prop>
                  <tuv xml:lang="en">
                    <seg>A <bpt i="1">&lt;b&gt;</bpt>bold<ept i="1">&lt;/b&gt;</ept> <ph>&lt;br&gt;</ph>move &amp; more</seg>
                  </tuv>
                  <tuv xml:lang="fr-CA">
                    <seg>Un coup <bpt i="1">&lt;b&gt;</bpt>audacieux<ept i="1">&lt;/b&gt;</ept> <ph>&lt;br&gt;</ph>et plus</seg>
                  </tuv>
                </tu>
                <tu>
                  <tuv xml:lang="en">
                    <seg><bpt i="1">&lt;i&gt;</bpt>open <ph>&lt;b&gt;</ph>x<ept i="1">&lt;/i&gt;</ept> end<ph>&lt;/b&gt;</ph></seg>
                  </tuv>
                  <tuv xml:lang="fr">
                    <seg>Fin</seg>
                  </tuv>
                </tu>
              </body>
            </tmx>

            XML, $xml);
    }

    public function testPairsNestedTagsAndLeavesSelfClosingOnesAlone(): void
    {
        $xml = new TmxWriter()->write(LanguageTag::of('en'), [new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<a href="/x"><B>in</b></a><img src="i.png"/><span>', 'x')]);

        self::assertStringContainsString('<seg><bpt i="2">&lt;a href="/x"&gt;</bpt><bpt i="1">&lt;B&gt;</bpt>in<ept i="1">&lt;/b&gt;</ept><ept i="2">&lt;/a&gt;</ept><ph>&lt;img src="i.png"/&gt;</ph><ph>&lt;span&gt;</ph></seg>', $xml);
    }

    /**
     * A closing tag pairs with the nearest opening tag of its name, and only with it.
     */
    public function testATagNestedInATagOfTheSameNamePairsWithTheNearest(): void
    {
        $xml = new TmxWriter()->write(LanguageTag::of('en'), [new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<b>a<b>b</b>c</b>', 'x')]);

        self::assertStringContainsString('<seg><bpt i="2">&lt;b&gt;</bpt>a<bpt i="1">&lt;b&gt;</bpt>b<ept i="1">&lt;/b&gt;</ept>c<ept i="2">&lt;/b&gt;</ept></seg>', $xml);
    }

    public function testALoneClosingTagDoesNotPairWithASelfClosingOne(): void
    {
        $xml = new TmxWriter()->write(LanguageTag::of('en'), [new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<br/>one</br><!-- note -->two', 'x')]);

        self::assertStringContainsString('<seg><ph>&lt;br/&gt;</ph>one<ph>&lt;/br&gt;</ph><ph>&lt;!-- note --&gt;</ph>two</seg>', $xml);
    }

    public function testReadsBackWhatItWrites(): void
    {
        $units = [
            new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr-CA'), 'A <b>bold</b> <br>move &amp; more', 'Un coup <b>audacieux</b>', 'page:1'),
            new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'Plain', 'Simple'),
        ];

        self::assertEquals($units, new TmxReader()->read(new TmxWriter()->write(LanguageTag::of('en'), $units)));
    }

    public function testReadsTheTargetsOfEachUnit(): void
    {
        $units = new TmxReader()->read(<<<'XML'
            <?xml version="1.0"?>
            <tmx version="1.4"><header srclang="en-us" creationtool="x" creationtoolversion="1" segtype="sentence" o-tmf="x" adminlang="en" datatype="plaintext"/>
            <body>
              <tu><prop type="x-other">ignored</prop><prop type="x-origin">file.po</prop>
                <tuv lang="FR"><seg>Le <hi>chat</hi> <it pos="begin">&lt;i&gt;</it>noir</seg></tuv>
                <tuv xml:lang="en-US"><seg>The <hi>cat</hi> <it pos="begin">&lt;i&gt;</it>black &lt; white</seg></tuv>
                <tuv xml:lang="de"><seg>  </seg></tuv>
                <tuv xml:lang="es"/>
                <tuv xml:lang="it"><seg>Il <sub>gatto</sub></seg></tuv>
              </tu>
              <tu srclang="fr"><tuv xml:lang="fr"><seg>Oui</seg></tuv><tuv xml:lang="en"><seg>Yes</seg></tuv></tu>
              <tu><tuv xml:lang="de"><seg>Ja</seg></tuv><tuv xml:lang="fr"><seg>Oui</seg></tuv></tu>
              <tu><tuv xml:lang="en"><seg> </seg></tuv><tuv xml:lang="fr"><seg>Rien</seg></tuv></tu>
            </body></tmx>
            XML);

        self::assertSame([
            ['en-US', 'fr', 'The cat <i>black &lt; white', 'Le chat <i>noir', 'file.po'],
            ['en-US', 'it', 'The cat <i>black &lt; white', 'Il gatto', 'file.po'],
            ['fr', 'en', 'Oui', 'Yes', null],
        ], array_map(static fn (MemoryUnit $unit): array => [$unit->sourceLanguage->value, $unit->targetLanguage->value, $unit->source, $unit->target, $unit->origin], $units));
    }

    /**
     * Of two variants in the source language, the first is the source; the other is a target like any.
     */
    public function testTakesTheFirstVariantInTheSourceLanguage(): void
    {
        $units = new TmxReader()->read('<tmx version="1.4"><header srclang="en"/><body><tu><tuv xml:lang="en"><seg>One</seg></tuv><tuv xml:lang="en"><seg>Two</seg></tuv><tuv xml:lang="fr"><seg>Un</seg></tuv></tu></body></tmx>');

        self::assertSame([['One', 'Two'], ['One', 'Un']], array_map(static fn (MemoryUnit $unit): array => [$unit->source, $unit->target], $units));
    }

    public function testTakesTheFirstVariantAsTheSourceOfAllLanguages(): void
    {
        $units = new TmxReader()->read('<tmx version="1.4"><header srclang="*all*"/><body><tu><tuv xml:lang="de"><seg>Ja</seg></tuv><tuv xml:lang="fr"><seg>Oui</seg></tuv></tu></body></tmx>');

        self::assertSame([['de', 'fr']], array_map(static fn (MemoryUnit $unit): array => [$unit->sourceLanguage->value, $unit->targetLanguage->value], $units));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 'the text is not well-formed XML with a <tmx> root and no document type.'];
        yield 'not XML' => ['<tmx', 'the text is not well-formed XML with a <tmx> root and no document type.'];
        yield 'document type' => ['<!DOCTYPE tmx SYSTEM "tmx14.dtd"><tmx/>', 'the text is not well-formed XML with a <tmx> root and no document type.'];
        yield 'document type in UTF-16' => [mb_convert_encoding('<?xml version="1.0" encoding="UTF-16LE"?><!DOCTYPE tmx [<!ENTITY a "b">]><tmx/>', 'UTF-16LE', 'UTF-8'), 'the text is not well-formed XML with a <tmx> root and no document type.'];
        yield 'another root' => ['<xliff/>', 'the text is not well-formed XML with a <tmx> root and no document type.'];
        yield 'no header' => ['<tmx><body/></tmx>', 'a TMX file holds a <header> and a <body>.'];
        yield 'no body' => ['<tmx><header srclang="en"/></tmx>', 'a TMX file holds a <header> and a <body>.'];
        yield 'no source language' => ['<tmx><header/><body><tu><tuv xml:lang="en"><seg>a</seg></tuv></tu></body></tmx>', '"" is not a language tag'];
        yield 'bad language' => ['<tmx><header srclang="en"/><body><tu><tuv xml:lang="e"><seg>a</seg></tuv></tu></body></tmx>', '"e" is not a language tag'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatIsNotTmx(string $xml, string $message): void
    {
        $this->expectException(InvalidTmx::class);
        $this->expectExceptionMessageIsOrContains('The TMX file cannot be read: '.$message);

        new TmxReader()->read($xml);
    }

    public function testReadsOneHundredThousandUnitsAtMost(): void
    {
        $tus = str_repeat('<tu/>', TmxReader::MAX_UNITS);
        self::assertSame([], new TmxReader()->read('<tmx><header srclang="en"/><body>'.$tus.'</body></tmx>'));

        $this->expectException(InvalidTmx::class);
        $this->expectExceptionMessageIsOrContains('a file holds at most 100000 units.');
        new TmxReader()->read('<tmx><header srclang="en"/><body>'.$tus.'<tu/></body></tmx>');
    }
}
