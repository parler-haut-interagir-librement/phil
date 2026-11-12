<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Tbx;

use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tbx\TbxReader;
use Phil\TranslationMemory\Tbx\TbxWriter;
use Phil\TranslationMemory\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TbxWriter::class)]
#[CoversClass(TbxReader::class)]
#[CoversClass(Xml::class)]
#[CoversClass(InvalidTbx::class)]
final class TbxTest extends TestCase
{
    public function testWritesAGlossaryAsTbx(): void
    {
        $xml = new TbxWriter()->write($this->glossary());

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <tbx xmlns="urn:iso:std:iso:30042:ed-2" type="TBX-Basic" style="dca" xml:lang="en">
              <tbxHeader>
                <fileDesc>
                  <sourceDesc>
                    <p>phil/translation-memory</p>
                  </sourceDesc>
                </fileDesc>
              </tbxHeader>
              <text>
                <body>
                  <conceptEntry id="c1">
                    <note>Health of a creature</note>
                    <langSec xml:lang="en">
                      <termSec>
                        <term>hit points</term>
                        <termNote type="administrativeStatus">preferredTerm-admn-sts</termNote>
                      </termSec>
                      <termSec>
                        <term>hit point</term>
                        <termNote type="administrativeStatus">admittedTerm-admn-sts</termNote>
                      </termSec>
                    </langSec>
                    <langSec xml:lang="fr-CA">
                      <termSec>
                        <term>points de vie</term>
                        <termNote type="administrativeStatus">preferredTerm-admn-sts</termNote>
                      </termSec>
                      <termSec>
                        <term>PV</term>
                        <termNote type="administrativeStatus">admittedTerm-admn-sts</termNote>
                      </termSec>
                      <termSec>
                        <term>point de vie</term>
                        <termNote type="administrativeStatus">admittedTerm-admn-sts</termNote>
                      </termSec>
                      <termSec>
                        <term>vie</term>
                        <termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote>
                      </termSec>
                    </langSec>
                  </conceptEntry>
                  <conceptEntry id="c2">
                    <langSec xml:lang="en">
                      <termSec>
                        <term>Gnome</term>
                        <termNote type="administrativeStatus">preferredTerm-admn-sts</termNote>
                      </termSec>
                    </langSec>
                    <langSec xml:lang="fr-CA">
                      <termSec>
                        <term>Gnome</term>
                        <termNote type="administrativeStatus">preferredTerm-admn-sts</termNote>
                      </termSec>
                    </langSec>
                  </conceptEntry>
                </body>
              </text>
            </tbx>

            XML, $xml);
    }

    public function testReadsBackWhatItWrites(): void
    {
        $glossary = new TbxReader()->read(new TbxWriter()->write($this->glossary()), LanguageTag::of('en'), LanguageTag::of('fr-CA'));

        self::assertSame([
            ['hit points', ['points de vie', 'PV', 'point de vie'], ['vie'], ['hit point'], 'Health of a creature'],
            ['Gnome', ['Gnome'], [], [], null],
        ], $this->describe($glossary));
        self::assertSame(['en', 'fr-CA'], [$glossary->sourceLanguage->value, $glossary->targetLanguage->value]);
    }

    public function testReadsTheTbxOf2008(): void
    {
        $glossary = new TbxReader()->read(<<<'XML'
            <?xml version="1.0"?>
            <martif type="TBX" xml:lang="en"><martifHeader/><text><body>
              <termEntry id="t1">
                <descrip type="subjectField">games</descrip>
                <descrip type="definition"> Health  of a creature </descrip>
                <langSet xml:lang="en">
                  <tig><term>HP</term><termNote type="administrativeStatus">admittedTerm-admn-sts</termNote></tig>
                  <ntig><termGrp><term>hit points</term><termNote type="administrativeStatus">preferredTerm-admn-sts</termNote></termGrp></ntig>
                  <tig><term>health</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></tig>
                </langSet>
                <langSet xml:lang="fr">
                  <tig><term>points de vie</term></tig>
                  <tig><term>vie</term><termNote type="normativeAuthorization">supersededTerm</termNote></tig>
                  <tig><term>santé</term><termNote type="administrativeStatus">notRecommended-admn-sts</termNote></tig>
                  <tig><term> </term></tig>
                  <tig><term>points de vie</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></tig>
                </langSet>
              </termEntry>
              <termEntry><langSet xml:lang="en"><tig><term>orphan</term></tig></langSet></termEntry>
              <termEntry><langSet xml:lang="en"><tig><term>gone</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></tig></langSet><langSet xml:lang="fr"><tig><term>parti</term></tig></langSet></termEntry>
              <termEntry><note> </note><langSet xml:lang="en"><tig><term>dice</term></tig></langSet><langSet xml:lang="fr-CA"><tig><term>dés</term></tig></langSet><langSet xml:lang="fr"><tig><term>dés de France</term></tig></langSet></termEntry>
              <termEntry><langSet xml:lang="en"><tig><term>only deprecated</term></tig></langSet><langSet xml:lang="fr"><tig><term>vieux</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></tig></langSet></termEntry>
            </body></text></martif>
            XML, LanguageTag::of('en-CA'), LanguageTag::of('fr-CA'));

        self::assertSame([
            ['hit points', ['points de vie'], ['vie', 'santé'], ['HP'], 'Health of a creature'],
            ['dice', ['dés'], [], [], null],
            ['only deprecated', ['only deprecated'], ['vieux'], [], null],
        ], $this->describe($glossary));
    }

    public function testATermWithoutAPreferredFormTakesTheFirstAllowed(): void
    {
        $glossary = new TbxReader()->read('<tbx><text><body><conceptEntry><langSec xml:lang="en"><termSec><term>old</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></termSec><termSec><term>first</term></termSec><termSec><term>second</term></termSec></langSec><langSec xml:lang="fr"><termSec><term>premier</term></termSec></langSec></conceptEntry></body></text></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertSame([['first', ['premier'], [], ['second'], null]], $this->describe($glossary));
    }

    public function testTermsMadeOfDigitsStayTexts(): void
    {
        $glossary = new TbxReader()->read('<tbx><text><body><conceptEntry><langSec xml:lang="en"><termSec><term>20</term><termNote type="administrativeStatus">preferredTerm-admn-sts</termNote></termSec><termSec><term>007</term></termSec><termSec><term>42</term></termSec></langSec><langSec xml:lang="fr"><termSec><term>vingt</term></termSec><termSec><term>13</term></termSec><termSec><term>14</term><termNote type="administrativeStatus">deprecatedTerm-admn-sts</termNote></termSec></langSec></conceptEntry></body></text></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertSame([['20', ['vingt', '13'], ['14'], ['007', '42'], null]], $this->describe($glossary));
    }

    public function testAStatusIsReadWhereverItIsWrittenAndTrimmed(): void
    {
        $glossary = new TbxReader()->read("<tbx><text><body><conceptEntry><langSec xml:lang=\"en\"><termSec><term> </term></termSec><termSec><term>first</term></termSec><termSec><term>second</term><termNote type=\"administrativeStatus\">\n  deprecatedTerm-admn-sts\n</termNote></termSec><termSec><term>third</term><termNote type=\"administrativeStatus\">admitted-notRecommended</termNote></termSec></langSec><langSec xml:lang=\"fr\"><termSec><term>premier</term></termSec></langSec></conceptEntry></body></text></tbx>", LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertSame([['first', ['premier'], [], ['third'], null]], $this->describe($glossary));
    }

    public function testLeavesNoLibxmlErrorBehindAndRestoresTheErrorMode(): void
    {
        foreach ([false, true] as $mode) {
            libxml_use_internal_errors($mode);
            libxml_clear_errors();

            try {
                new TbxReader()->read('<tbx><unclosed></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'));
                self::fail('The file is not well-formed.');
            } catch (InvalidTbx) {
                self::assertSame([], libxml_get_errors());
                self::assertSame($mode, libxml_use_internal_errors(false));
            }
        }
    }

    public function testReadsNothingFromAFileWithoutBody(): void
    {
        self::assertSame([], new TbxReader()->read('<tbx/>', LanguageTag::of('en'), LanguageTag::of('fr'))->terms);
        self::assertSame([], new TbxReader()->read('<tbx><text/></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'))->terms);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => ['', 'the text is not well-formed XML with a <tbx> or <martif> root and no document type.'];
        yield 'not XML' => ['<tbx>', 'the text is not well-formed XML with a <tbx> or <martif> root and no document type.'];
        yield 'document type' => ['<!DOCTYPE martif SYSTEM "TBXcoreStructV02.dtd"><martif/>', 'the text is not well-formed XML with a <tbx> or <martif> root and no document type.'];
        yield 'another root' => ['<tmx/>', 'the text is not well-formed XML with a <tbx> or <martif> root and no document type.'];
        yield 'bad language' => ['<tbx><text><body><conceptEntry><langSec xml:lang="1"/></conceptEntry></body></text></tbx>', '"1" is not a language tag'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatIsNotTbx(string $xml, string $message): void
    {
        $this->expectException(InvalidTbx::class);
        $this->expectExceptionMessageIsOrContains('The TBX file cannot be read: '.$message);

        new TbxReader()->read($xml, LanguageTag::of('en'), LanguageTag::of('fr'));
    }

    public function testReadsOneHundredThousandEntriesAtMost(): void
    {
        $entries = str_repeat('<conceptEntry/>', TbxReader::MAX_ENTRIES);
        self::assertSame([], new TbxReader()->read('<tbx><text><body>'.$entries.'</body></text></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'))->terms);

        $this->expectException(InvalidTbx::class);
        $this->expectExceptionMessageIsOrContains('a file holds at most 100000 entries.');
        new TbxReader()->read('<tbx><text><body>'.$entries.'<conceptEntry/></body></text></tbx>', LanguageTag::of('en'), LanguageTag::of('fr'));
    }

    private function glossary(): Glossary
    {
        return new Glossary(LanguageTag::of('en'), LanguageTag::of('fr-CA'), [
            new Term('hit points', ['points de vie', 'PV'], ['vie', 'PV'], true, 'Health of a creature', ['hit point'], ['point de vie']),
            new Term('Gnome'),
        ]);
    }

    /**
     * @return list<array{string, list<string>, list<string>, list<string>, ?string}>
     */
    private function describe(Glossary $glossary): array
    {
        return array_map(static fn (Term $term): array => [$term->source, $term->accepted(), $term->forbidden, $term->forms, $term->note], $glossary->terms);
    }
}
