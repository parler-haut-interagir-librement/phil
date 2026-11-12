<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Application;

use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\Glossary\RegularPlurals;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tbx\TbxReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Glossaries::class)]
#[CoversClass(NotFound::class)]
final class GlossariesTest extends TestCase
{
    public function testPutsTermsInTheGlossaryOfTheirLanguages(): void
    {
        $glossaries = new Stores()->glossaries;
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie']));
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon', ['dragon']));
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('de'), new Term('dragon', ['Drache']));

        $record = $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['PV']));

        self::assertSame(['PV'], $record->term()->translations);
        self::assertSame(['dragon', 'hit points'], array_map(static fn (TermRecord $record): string => $record->source(), $glossaries->terms(LanguageTag::of('en'), LanguageTag::of('fr'))));

        $glossary = $glossaries->glossary(LanguageTag::of('en'), LanguageTag::of('fr'));
        self::assertSame(['en', 'fr', ['hit points', 'dragon']], [$glossary->sourceLanguage->value, $glossary->targetLanguage->value, array_map(static fn (Term $term): string => $term->source, $glossary->terms)]);
        self::assertSame(['PV'], $glossary->terms[0]->translations);
    }

    public function testRemovesATerm(): void
    {
        $glossaries = new Stores()->glossaries;
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon'));
        $glossaries->remove(LanguageTag::of('en'), LanguageTag::of('fr'), 'dragon');

        self::assertSame([], $glossaries->terms(LanguageTag::of('en'), LanguageTag::of('fr')));

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('The glossary from "en" to "fr" has no term "dragon".');

        $glossaries->remove(LanguageTag::of('en'), LanguageTag::of('fr'), 'dragon');
    }

    public function testAddsTheFormsOfItsInflector(): void
    {
        $stores = new Stores();
        $glossaries = new Glossaries($stores->terms, new RegularPlurals());
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit point', ['point de vie']));

        self::assertSame(['hit points'], $glossaries->glossary(LanguageTag::of('en'), LanguageTag::of('fr'))->terms[0]->forms);
        self::assertSame([], $stores->glossaries->glossary(LanguageTag::of('en'), LanguageTag::of('fr'))->terms[0]->forms);
        self::assertStringNotContainsString('hit points', $glossaries->export(LanguageTag::of('en'), LanguageTag::of('fr')));
    }

    public function testExportsAndImportsTbx(): void
    {
        $glossaries = new Stores()->glossaries;
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie'], ['vie'], note: 'Health.', forms: ['hit point']));
        $glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon', ['dragon']));

        $tbx = $glossaries->export(LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertSame(['dragon', 'hit points'], array_map(static fn (Term $term): string => $term->source, new TbxReader()->read($tbx, LanguageTag::of('en'), LanguageTag::of('fr'))->terms));

        $other = new Stores()->glossaries;
        $other->put(LanguageTag::of('en'), LanguageTag::of('fr-CA'), new Term('dragon', ['wyvern']));
        self::assertSame(2, $other->import(LanguageTag::of('en'), LanguageTag::of('fr-CA'), $tbx));
        self::assertEquals(
            [new Term('dragon', ['dragon']), new Term('hit points', ['points de vie'], ['vie'], note: 'Health.', forms: ['hit point'])],
            array_map(static fn (TermRecord $record): Term => $record->term(), $other->terms(LanguageTag::of('en'), LanguageTag::of('fr-CA'))),
        );
    }

    public function testRefusesAFileThatIsNotTbx(): void
    {
        $this->expectException(InvalidTbx::class);

        new Stores()->glossaries->import(LanguageTag::of('en'), LanguageTag::of('fr'), '<xliff/>');
    }
}
