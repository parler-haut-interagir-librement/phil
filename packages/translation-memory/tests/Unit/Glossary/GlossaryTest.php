<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Glossary;

use Phil\TranslationMemory\Exception\InvalidTerm;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\Glossary\TermHit;
use Phil\TranslationMemory\Glossary\TermIssue;
use Phil\TranslationMemory\Glossary\TermIssueKind;
use Phil\TranslationMemory\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Glossary::class)]
#[CoversClass(Term::class)]
#[CoversClass(TermHit::class)]
#[CoversClass(TermIssue::class)]
#[CoversClass(TermIssueKind::class)]
#[CoversClass(InvalidTerm::class)]
final class GlossaryTest extends TestCase
{
    public function testFindsTermsAsWholeWordsInReadingOrder(): void
    {
        $hits = $this->glossary()->find('The <b>hit  points</b> of a Dragon, and the dragonfly. Hit points!');

        self::assertSame([['hit points', 4], ['Dragon', 20], ['Hit points', 47]], array_map(static fn (TermHit $hit): array => [$hit->text, $hit->offset], $hits));
        self::assertSame('hit points', $hits[0]->term->source);
    }

    public function testTheLongerOfTwoOverlappingTermsWins(): void
    {
        $glossary = new Glossary($this->en(), $this->fr(), [new Term('points', ['points']), new Term('hit points', ['points de vie']), new Term('hit', ['coup'])]);

        self::assertSame(['hit points', 'hit'], array_map(static fn (TermHit $hit): string => $hit->text, $glossary->find('hit points then a hit')));
    }

    public function testACaseSensitiveTermMatchesItsCaseOnly(): void
    {
        $glossary = new Glossary($this->en(), $this->fr(), [new Term('Gnome', ['Gnome'], caseSensitive: true)]);

        self::assertSame(['Gnome'], array_map(static fn (TermHit $hit): string => $hit->text, $glossary->find('a gnome and a Gnome')));
        self::assertSame(5, $glossary->find('Été: Gnome')[0]->offset);
    }

    public function testChecksThatEachTermIsTranslated(): void
    {
        $glossary = $this->glossary();

        self::assertSame([], $glossary->check('The hit points of the dragon.', 'Les points de vie du dragon.'));
        self::assertSame([], $glossary->check('The hit points of the dragon.', 'Les PV du Dragon.'));
        self::assertSame(
            [['missing', 'hit points', 1, 0, null]],
            $this->describe($glossary->check('The hit points.', 'Les points de vitalité.')),
        );
    }

    public function testCountsEachUseOfATerm(): void
    {
        self::assertSame(
            [['missing', 'hit points', 2, 1, null]],
            $this->describe($this->glossary()->check('Hit points and hit points.', 'Points de vie et vitalité.')),
        );
        self::assertSame([], $this->glossary()->check('Hit points and hit points.', 'Points de vie et PV.'));
    }

    public function testReportsARefusedTranslation(): void
    {
        self::assertSame(
            [['forbidden', 'hit points', 0, 0, 'Vie'], ['forbidden', 'hit points', 0, 0, 'vie']],
            $this->describe($this->glossary()->check('The hit points.', 'Les points de vie, la Vie et la vie.')),
        );
    }

    public function testARefusedWordAtTheStartOfAnAllowedTranslationIsAllowed(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [new Term('eternal life', ['vie éternelle'], ['vie'])]);

        self::assertSame([], $glossary->check('The eternal life.', 'La vie éternelle.'));
    }

    public function testTwoTermsSideBySideAreBothFound(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [new Term('a.'), new Term('.b')]);

        self::assertSame(['a.', '.b'], array_map(static fn (TermHit $hit): string => $hit->text, $glossary->find('a..b')));
    }

    public function testARefusedWordRightAfterAnAllowedTranslationIsReported(): void
    {
        self::assertSame([['forbidden', 'hit points', 0, 0, 'vie']], $this->describe($this->glossary()->check('The hit points.', 'Les points de vie vie.')));
    }

    public function testATermIsTakenLiterallyNotAsAPattern(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [new Term('a.b', ['a.b']), new Term('C++', ['C++'])]);

        self::assertSame(['a.b', 'C++'], array_map(static fn (TermHit $hit): string => $hit->text, $glossary->find('axb a.b and C++ or Cpp')));
    }

    public function testOffsetsAreInCharacters(): void
    {
        $glossary = new Glossary(LanguageTag::of('en'), LanguageTag::of('fr'), [new Term('été'), new Term('été chaud'), new Term('x')]);

        self::assertSame([['Été chaud', 0], ['x', 10], ['été', 12]], array_map(static fn (TermHit $hit): array => [$hit->text, $hit->offset], $glossary->find('Été chaud x été')));
    }

    public function testAKeptTermMustStayAsItIs(): void
    {
        $glossary = new Glossary($this->en(), $this->fr(), [new Term('Dungeon Master')]);

        self::assertSame([], $glossary->check('Ask the Dungeon Master.', 'Demandez au Dungeon Master.'));
        self::assertSame([['missing', 'Dungeon Master', 1, 0, null]], $this->describe($glossary->check('Ask the Dungeon Master.', 'Demandez au maître du donjon.')));
    }

    public function testATermAbsentFromTheSourceIsNotChecked(): void
    {
        self::assertSame([], $this->glossary()->check('A goblin.', 'Un gobelin, sa vie.'));
    }

    public function testATermIsNormalized(): void
    {
        $term = new Term('  hit  points ', [' points  de vie '], ['vie '], note: 'Not "vie" alone.');

        self::assertSame('hit points', $term->source);
        self::assertSame(['points de vie'], $term->translations);
        self::assertSame(['vie'], $term->forbidden);
        self::assertFalse($term->caseSensitive);
        self::assertSame('Not "vie" alone.', $term->note);
        self::assertFalse($term->isKept());
        self::assertSame(['points de vie'], $term->expected());
        self::assertTrue(new Term('Melee')->isKept());
        self::assertSame(['Melee'], new Term('Melee')->expected());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function invalidTerms(): iterable
    {
        yield 'empty term' => [' ', [], []];
        yield 'empty translation' => ['a', [''], []];
        yield 'empty refused' => ['a', ['b'], [' ']];
        yield 'not UTF-8' => ["\xFF", [], []];
    }

    /**
     * @param list<string> $translations
     * @param list<string> $forbidden
     */
    #[DataProvider('invalidTerms')]
    public function testRefusesATermWithoutWords(string $source, array $translations, array $forbidden): void
    {
        $this->expectException(InvalidTerm::class);
        $this->expectExceptionMessageIsOrContains('A glossary term, its translations and their forms are texts of UTF-8 that hold words.');

        new Term($source, $translations, $forbidden);
    }

    public function testKnowsItsLanguages(): void
    {
        self::assertSame(['en', 'fr'], [$this->glossary()->sourceLanguage->value, $this->glossary()->targetLanguage->value]);
        self::assertSame([], new Glossary($this->en(), $this->fr())->find('anything'));
    }

    private function glossary(): Glossary
    {
        return new Glossary($this->en(), $this->fr(), [
            new Term('hit points', ['points de vie', 'PV'], ['vie']),
            new Term('dragon', ['dragon']),
        ]);
    }

    /**
     * @param list<TermIssue> $issues
     *
     * @return list<array{string, string, int, int, ?string}>
     */
    private function describe(array $issues): array
    {
        return array_map(static fn (TermIssue $issue): array => [$issue->kind->value, $issue->term->source, $issue->expected, $issue->found, $issue->forbidden], $issues);
    }

    private function en(): LanguageTag
    {
        return LanguageTag::of('en');
    }

    private function fr(): LanguageTag
    {
        return LanguageTag::of('fr');
    }
}
