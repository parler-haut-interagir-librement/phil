<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Glossary;

use Override;
use Phil\TranslationMemory\Exception\InvalidTerm;
use Phil\TranslationMemory\Glossary\Contract\Inflector;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\RegularPlurals;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\Glossary\TermHit;
use Phil\TranslationMemory\Glossary\TermIssue;
use Phil\TranslationMemory\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Term::class)]
#[CoversClass(Glossary::class)]
#[CoversClass(RegularPlurals::class)]
final class InflectionTest extends TestCase
{
    public function testATermKeepsItsFormsApartFromItself(): void
    {
        $term = new Term(' hit  points ', ['points de vie'], forms: ['hit point', 'hit points', 'hit  point'], translationForms: ['point de vie', 'points de vie']);

        self::assertSame(['hit point'], $term->forms);
        self::assertSame(['point de vie'], $term->translationForms);
        self::assertSame(['hit points', 'hit point'], $term->variants());
        self::assertSame(['points de vie', 'point de vie'], $term->accepted());
        self::assertSame(['points de vie'], $term->expected());
    }

    public function testAKeptTermAcceptsItsFormsInTheTarget(): void
    {
        $term = new Term('Gnome', forms: ['Gnomes']);

        self::assertSame(['Gnome'], $term->expected());
        self::assertSame(['Gnome'], $term->accepted());
        self::assertSame(['Gnome', 'Gnomes'], $term->inflected(new RegularPlurals(), LanguageTag::of('en'), LanguageTag::of('fr'))->accepted());
    }

    public function testNormalizesTheTranslationFormsAndKeepsThemInOrderFromZero(): void
    {
        $term = new Term('hit points', ['points de vie'], forms: ['hit points', 'hit  point'], translationForms: ['points de vie', 'point  de   vie', 'vies']);

        self::assertSame(['hit point'], $term->forms);
        self::assertSame(['point de vie', 'vies'], $term->translationForms);
    }

    public function testRefusesAFormWithoutWords(): void
    {
        $this->expectException(InvalidTerm::class);

        new Term('a', ['b'], forms: [' ']);
    }

    public function testRefusesATranslationFormWithoutWords(): void
    {
        $this->expectException(InvalidTerm::class);

        new Term('a', ['b'], translationForms: ['']);
    }

    public function testFindsAndAcceptsTheFormsOfATerm(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [new Term('hit points', ['points de vie'], ['vie'], forms: ['hit point'], translationForms: ['point de vie'])]);

        self::assertSame([['hit point', 2], ['hit points', 20]], array_map(static fn (TermHit $hit): array => [$hit->text, $hit->offset], $glossary->find('1 hit point, then 2 hit points')));
        self::assertSame([], $glossary->check('1 hit point, 2 hit points', '1 point de vie, 2 points de vie'));
        self::assertSame([['missing', 2, 1]], array_map(static fn (TermIssue $issue): array => [$issue->kind->value, $issue->expected, $issue->found], $glossary->check('1 hit point, 2 hit points', '1 point de vie, 2 vies')));
    }

    public function testAnInflectorAddsFormsToEveryTerm(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr-CA'), [new Term('hit point', ['point de vie']), new Term('sword', ['épée longue'], forms: ['swords'])], new RegularPlurals());

        self::assertSame(['hit points'], $glossary->terms[0]->forms);
        self::assertSame(['points de vie'], $glossary->terms[0]->translationForms);
        self::assertSame(['swords'], $glossary->terms[1]->forms);
        self::assertSame(['épées longues'], $glossary->terms[1]->translationForms);
        self::assertSame([], $glossary->check('Two hit points and swords.', 'Deux points de vie et des épées longues.'));
    }

    public function testAnInflectorThatKnowsNothingChangesNothing(): void
    {
        $inflector = new class implements Inflector {
            #[Override]
            public function forms(string $phrase, LanguageTag $language): array
            {
                return [];
            }
        };

        $term = new Term('hit point', ['point de vie'], ['vie'], true, 'Note', ['hit points'], ['points de vie']);
        $inflected = $term->inflected($inflector, LanguageTag::of('en'), LanguageTag::of('fr'));

        self::assertEquals($term, $inflected);
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function plurals(): iterable
    {
        yield 'English' => ['hit point', 'en', ['hit points']];
        yield 'English box' => ['box', 'en-CA', ['boxes']];
        yield 'English church' => ['church', 'en', ['churches']];
        yield 'English wish' => ['wish', 'en', ['wishes']];
        yield 'English quiz' => ['quiz', 'en', ['quizes']];
        yield 'English city' => ['city', 'en', ['cities']];
        yield 'English day' => ['day', 'en', ['days']];
        yield 'English already plural' => ['dice', 'en', ['dices']];
        yield 'English plural kept' => ['bus', 'en', ['buses']];
        yield 'English with a digit' => ['d20', 'en', []];
        yield 'French' => ['point de vie', 'fr', ['points de vie']];
        yield 'French adjective' => ['épée longue', 'fr-CA', ['épées longues']];
        yield 'French -al' => ['cheval', 'fr', ['chevaux']];
        yield 'French -al exception' => ['festival', 'fr', ['festivals']];
        yield 'French -eau' => ['bateau', 'fr', ['bateaux']];
        yield 'French -eu' => ['jeu de rôle', 'fr', ['jeux de rôle']];
        yield 'French -eu exception' => ['pneu', 'fr', ['pneus']];
        yield 'French -ou' => ['Bijou', 'fr', ['Bijoux']];
        yield 'French -ou regular' => ['trou', 'fr', ['trous']];
        yield 'French already plural' => ['dés', 'fr', []];
        yield 'French nez' => ['nez', 'fr', []];
        yield 'French apostrophe' => ["main-d'œuvre", 'fr', []];
        yield 'French preposition first' => ['à la carte', 'fr', []];
        yield 'French capital preposition' => ['carte De visite', 'fr', ['cartes De visite']];
        yield 'French upper case' => ['Cheval', 'fr', ['Chevaux']];
        yield 'English digits before letters' => ['3box', 'en', []];
        yield 'English accent' => ['café', 'en', ['cafés']];
        yield 'English upper case' => ['BOX', 'en', ['BOXes']];
        yield 'English upper case y' => ['CITY', 'en', ['CITies']];
        yield 'English y inside' => ['myth', 'en', ['myths']];
        yield 'English long s' => ['maſ', 'en', ['maſes']];
        yield 'French accented capital preposition' => ['À la carte', 'fr', []];
        yield 'French accented capital exception' => ['Émeu', 'fr', ['Émeus']];
        yield 'French upper case x' => ['DOUX', 'fr', []];
        yield 'French long s' => ['maſ', 'fr', []];
        yield 'French upper case -eau' => ['BATEAU', 'fr', ['BATEAUx']];
        yield 'French eu inside' => ['jeune', 'fr', ['jeunes']];
        yield 'French upper case -al' => ['CHEVAL', 'fr', ['CHEVAux']];
        yield 'French al inside' => ['alpha', 'fr', ['alphas']];
        yield 'German' => ['Trefferpunkt', 'de', []];
    }

    /**
     * @param list<string> $forms
     */
    #[DataProvider('plurals')]
    public function testMakesTheRegularPlural(string $phrase, string $language, array $forms): void
    {
        self::assertSame($forms, new RegularPlurals()->forms($phrase, LanguageTag::of($language)));
    }
}
