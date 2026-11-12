<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Memory;

use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\MatchKind;
use Phil\TranslationMemory\Memory\MemoryMatch;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Variants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationMemory::class)]
#[CoversClass(InMemoryStore::class)]
#[CoversClass(MemoryUnit::class)]
#[CoversClass(MemoryMatch::class)]
#[CoversClass(MatchKind::class)]
#[CoversClass(InvalidConfiguration::class)]
final class TranslationMemoryTest extends TestCase
{
    public function testFindsAnExactMatchFirst(): void
    {
        $memory = new TranslationMemory($this->store());
        $matches = $memory->lookup('The quick  brown fox', $this->en(), $this->fr());

        self::assertSame([['exact', 1.0, 'Le renard brun rapide'], ['fuzzy', 0.89, 'Le chien brun rapide']], $this->describe($matches));
        self::assertSame([100, 89], array_map(static fn (MemoryMatch $match): int => $match->percent(), $matches));
    }

    public function testFindsCloseMatchesBestFirst(): void
    {
        $matches = new TranslationMemory($this->store())->lookup('The quick brown fox!', $this->en(), $this->fr());

        self::assertSame([['fuzzy', 0.95, 'Le renard brun rapide'], ['fuzzy', 0.85, 'Le chien brun rapide']], $this->describe($matches));
    }

    public function testTheSameWordsWithOtherTagsAreAFuzzyMatchBelowExact(): void
    {
        $matches = new TranslationMemory($this->store())->lookup('The <b>quick</b> brown fox', $this->en(), $this->fr());

        self::assertSame(['fuzzy', 0.99, 'Le renard brun rapide'], $this->describe($matches)[0]);
    }

    public function testKeepsMatchesAboveTheMinimumOnly(): void
    {
        self::assertSame([['fuzzy', 0.95, 'Le renard brun rapide']], $this->describe(new TranslationMemory($this->store(), 0.9)->lookup('The quick brown fox!', $this->en(), $this->fr())));
        self::assertSame([], new TranslationMemory($this->store(), 1.0)->lookup('The quick brown fox!', $this->en(), $this->fr()));
        self::assertSame([['exact', 1.0, 'Le renard brun rapide']], $this->describe(new TranslationMemory($this->store(), 1.0)->lookup('The quick brown fox', $this->en(), $this->fr())));
        self::assertSame([], new TranslationMemory($this->store(), 1.0)->lookup('The <b>quick</b> brown fox', $this->en(), $this->fr()));
    }

    public function testKeepsAMatchExactlyAtTheMinimum(): void
    {
        $store = new InMemoryStore([new MemoryUnit($this->en(), $this->fr(), 'abcd', 'un')]);

        self::assertSame([['fuzzy', 0.75, 'un']], $this->describe(new TranslationMemory($store, 0.75)->lookup('abcx', $this->en(), $this->fr())));
        self::assertSame([], new TranslationMemory($store, 0.76)->lookup('abcx', $this->en(), $this->fr()));
    }

    public function testLooksOnlyAtUnitsWhoseLengthCanReachTheMinimum(): void
    {
        $store = new InMemoryStore([
            new MemoryUnit($this->en(), $this->fr(), 'abcdefghij', 'ten'),
            new MemoryUnit($this->en(), $this->fr(), 'abcdefgh', 'eight'),
            new MemoryUnit($this->en(), $this->fr(), 'abcdefg', 'seven'),
            new MemoryUnit($this->en(), $this->fr(), 'abcdefghijkl', 'twelve'),
            new MemoryUnit($this->en(), $this->fr(), 'abcdefghijklm', 'thirteen'),
        ]);
        $seven = new InMemoryStore([new MemoryUnit($this->en(), $this->fr(), 'abcdefg', 'seven')]);

        self::assertSame(['ten', 'twelve', 'eight'], array_column($this->describe(new TranslationMemory($store, 0.8)->lookup('abcdefghij', $this->en(), $this->fr())), 2));
        self::assertSame([['fuzzy', 0.7, 'seven']], $this->describe(new TranslationMemory($seven, 0.7)->lookup('abcdefghij', $this->en(), $this->fr())));
    }

    public function testKeepsOneMatchPerTargetAndAtMostTheLimit(): void
    {
        $store = new InMemoryStore([
            new MemoryUnit($this->en(), $this->fr(), 'Open the door', 'Ouvrez la porte'),
            new MemoryUnit($this->en(), $this->fr(), 'Open the doors', 'Ouvrez la porte'),
            new MemoryUnit($this->en(), $this->fr(), 'Open the box', 'Ouvrez la boîte'),
            new MemoryUnit($this->en(), $this->fr(), 'Open the boxes', 'Ouvrez les boîtes'),
        ]);

        self::assertSame([['exact', 1.0, 'Ouvrez la porte'], ['fuzzy', 0.77, 'Ouvrez la boîte'], ['fuzzy', 0.71, 'Ouvrez les boîtes']], $this->describe(new TranslationMemory($store, 0.5)->lookup('Open the door', $this->en(), $this->fr())));
        self::assertSame(['Ouvrez la porte', 'Ouvrez la boîte'], array_column($this->describe(new TranslationMemory($store, 0.5, 2)->lookup('Open the door', $this->en(), $this->fr())), 2));
    }

    public function testScoresOnlyTheShortlistOfTheClosestTrigrams(): void
    {
        $store = new InMemoryStore([
            new MemoryUnit($this->en(), $this->fr(), 'abcdefxyzw', 'trigrams'),
            new MemoryUnit($this->en(), $this->fr(), 'zbcdefghij', 'edits'),
        ]);

        self::assertSame(['edits'], array_column($this->describe(new TranslationMemory($store, 0.5, 5, 1)->lookup('abcdefghij', $this->en(), $this->fr())), 2));
        self::assertSame(['edits', 'trigrams'], array_column($this->describe(new TranslationMemory($store, 0.5, 5, 2)->lookup('abcdefghij', $this->en(), $this->fr())), 2));
    }

    public function testAVariantReadsTheUnitsOfItsParentAfterItsOwn(): void
    {
        $canada = LanguageTag::of('fr-CA');
        $store = new InMemoryStore([
            new MemoryUnit($this->en(), $this->fr(), 'The quick brown fox', 'Le renard brun rapide'),
            new MemoryUnit($this->en(), $this->fr(), 'The quick brown dog', 'Le chien brun rapide'),
            new MemoryUnit($this->en(), $this->fr(), 'The quick red fox', 'Le renard roux'),
            new MemoryUnit($this->en(), $canada, 'The quick brown fox', 'Le renard brun vite'),
            new MemoryUnit($this->en(), $canada, 'The quick red fox', 'Le renard roux'),
        ]);

        self::assertSame(
            [['exact', 1.0, 'Le renard brun vite'], ['exact', 1.0, 'Le renard brun rapide'], ['fuzzy', 0.89, 'Le chien brun rapide'], ['fuzzy', 0.79, 'Le renard roux']],
            $this->describe(new TranslationMemory($store, 0.5)->lookup('The quick brown fox', $this->en(), $canada)),
        );
        self::assertSame(['fr-CA', 'fr'], array_map(static fn (MemoryMatch $match): string => $match->unit->targetLanguage->value, \array_slice(new TranslationMemory($store, 0.5)->lookup('The quick brown fox', $this->en(), $canada), 0, 2)));
        self::assertSame('fr-CA', new TranslationMemory($store, 0.5)->lookup('The quick red fox', $this->en(), $canada)[0]->unit->targetLanguage->value);
        self::assertSame([['exact', 1.0, 'Le renard brun vite']], $this->describe(new TranslationMemory($store, 1.0, 5, 50, Variants::none())->lookup('The quick brown fox', $this->en(), $canada)));
        self::assertSame([['exact', 1.0, 'Le renard brun rapide']], $this->describe(new TranslationMemory($store, 1.0)->lookup('The quick brown fox', $this->en(), $this->fr())));
        self::assertSame([], new TranslationMemory($store, 0.5)->lookup('The quick brown fox', $this->en(), LanguageTag::of('fr-BE')));
    }

    public function testLooksInOnePairOfLanguages(): void
    {
        self::assertSame([], new TranslationMemory($this->store())->lookup('The quick brown fox', $this->en(), LanguageTag::of('de')));
        self::assertSame([], new TranslationMemory($this->store())->lookup('The quick brown fox', LanguageTag::of('fr'), $this->en()));
        self::assertSame([], new TranslationMemory($this->store())->lookup(' <br> ', $this->en(), $this->fr()));
    }

    public function testLooksAtLengthsInCharacters(): void
    {
        $store = new InMemoryStore([new MemoryUnit(self::en(), self::fr(), 'ééééééééé', 'neuf')]);

        self::assertSame([['fuzzy', 0.9, 'neuf']], self::describe(new TranslationMemory($store, 0.85)->lookup('éééééééééé', self::en(), self::fr())));
    }

    public function testTheWindowOfLengthsLeavesOutWhatCannotReachTheMinimum(): void
    {
        $short = new InMemoryStore([new MemoryUnit(self::en(), self::fr(), 'abcdefg', 'short'), new MemoryUnit(self::en(), self::fr(), 'abcdefghXY', 'good')]);
        $long = new InMemoryStore([new MemoryUnit(self::en(), self::fr(), 'abcdefghijklm', 'long'), new MemoryUnit(self::en(), self::fr(), 'abcdefghXY', 'good')]);
        $double = new InMemoryStore([new MemoryUnit(self::en(), self::fr(), 'abcdefghijklmnopqrst', 'double')]);

        self::assertSame(['good'], array_column(self::describe(new TranslationMemory($short, 0.75, 5, 1)->lookup('abcdefghij', self::en(), self::fr())), 2));
        self::assertSame(['good'], array_column(self::describe(new TranslationMemory($long, 0.8, 5, 1)->lookup('abcdefghij', self::en(), self::fr())), 2));
        self::assertSame([['fuzzy', 0.5, 'double']], self::describe(new TranslationMemory($double, 0.5)->lookup('abcdefghij', self::en(), self::fr())));
    }

    public function testSortsMatchesByScoreRatherThanByTrigrams(): void
    {
        $store = new InMemoryStore([new MemoryUnit(self::en(), self::fr(), 'abcdefghijab', 'trigrams'), new MemoryUnit(self::en(), self::fr(), 'abXdefghij', 'edits')]);

        self::assertSame([['fuzzy', 0.9, 'edits'], ['fuzzy', 0.83, 'trigrams']], self::describe(new TranslationMemory($store, 0.8)->lookup('abcdefghij', self::en(), self::fr())));
    }

    public function testGivesFiveMatchesAndScoresFiftyCandidatesByDefault(): void
    {
        $units = [];

        foreach (['K', 'L', 'M', 'N', 'O', 'P', 'Q'] as $letter) {
            $units[] = new MemoryUnit(self::en(), self::fr(), 'abcdefghi'.$letter, $letter);
        }

        self::assertSame(['K', 'L', 'M', 'N', 'O'], array_column(self::describe(new TranslationMemory(new InMemoryStore($units))->lookup('abcdefghij', self::en(), self::fr())), 2));

        $decoys = [];

        foreach (str_split('KLMNOPQRSTUVWZ') as $first) {
            foreach (str_split('KLMNOPQRSTUVWZ') as $second) {
                $decoys[] = new MemoryUnit(self::en(), self::fr(), 'abcdefg'.$first.$second.'Y', 'decoy');
            }
        }

        $target = new MemoryUnit(self::en(), self::fr(), 'aXcdeXghij', 'target');

        self::assertSame(['target'], array_column(self::describe(new TranslationMemory(new InMemoryStore([...\array_slice($decoys, 0, 49), $target]), 0.75)->lookup('abcdefghij', self::en(), self::fr())), 2));
        self::assertSame([], new TranslationMemory(new InMemoryStore([...\array_slice($decoys, 0, 50), $target]), 0.75)->lookup('abcdefghij', self::en(), self::fr()));
    }

    public function testLearnsUsableSegments(): void
    {
        $store = new InMemoryStore();
        $memory = new TranslationMemory($store);

        self::assertTrue($memory->learn(new Segment('k', 'Hello')->translate('Bonjour'), $this->en(), $this->fr(), 'page:1'));
        self::assertTrue($memory->learn(new Segment('k', 'Bye')->translate('Salut')->review(), $this->en(), $this->fr()));
        self::assertFalse($memory->learn(new Segment('k', 'Yes')->suggest('Oui'), $this->en(), $this->fr()));
        self::assertFalse($memory->learn(new Segment('k', 'No'), $this->en(), $this->fr()));
        self::assertFalse($memory->learn(new Segment('k', '<br>')->translate('<br>'), $this->en(), $this->fr()));
        self::assertFalse($memory->learn(new Segment('k', 'Gap')->translate(' '), $this->en(), $this->fr()));
        self::assertCount(2, $store);

        $match = $memory->lookup('Hello', $this->en(), $this->fr())[0];
        self::assertSame(['Hello', 'Bonjour', 'page:1', 'Hello'], [$match->unit->source, $match->unit->target, $match->unit->origin, $match->unit->plain]);

        $memory->learn(new Segment('k', 'Hello')->translate('Allô'), $this->en(), $this->fr());
        self::assertCount(2, $store);
        self::assertSame('Allô', $memory->lookup('Hello', $this->en(), $this->fr())[0]->unit->target);
    }

    public function testTheStoreCountsTheUnitsOfEveryPairOfLanguages(): void
    {
        self::assertCount(4, $this->store());
    }

    public function testAUnitKnowsTheLengthOfItsWords(): void
    {
        $unit = new MemoryUnit($this->en(), $this->fr(), '<b>Été</b>  chaud', 'Hot summer');

        self::assertSame('Été chaud', $unit->plain);
        self::assertSame(9, $unit->length());
        self::assertNull($unit->origin);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUnits(): iterable
    {
        yield 'source without words' => ['<br>', 'x'];
        yield 'target without words' => ['x', ' '];
        yield 'not UTF-8' => ["\xFF", 'x'];
        yield 'target not UTF-8' => ['x', "\xFF"];
    }

    #[DataProvider('invalidUnits')]
    public function testRefusesAUnitWithoutWords(string $source, string $target): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains(mb_check_encoding($source.$target, 'UTF-8') ? 'A translation unit pairs a source and a target that both hold words.' : 'The texts of a translation unit must be valid UTF-8.');

        new MemoryUnit($this->en(), $this->fr(), $source, $target);
    }

    public function testRefusesAnOriginThatIsNotUtf8(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('The texts of a translation unit must be valid UTF-8.');

        new MemoryUnit($this->en(), $this->fr(), 'a', 'b', "\xFF");
    }

    /**
     * @return iterable<string, array{float, int, int, string}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'zero minimum' => [0.0, 5, 50, 'The minimum score is above 0 and at most 1; got 0.'];
        yield 'minimum above one' => [1.01, 5, 50, 'The minimum score is above 0 and at most 1; got 1.01.'];
        yield 'zero limit' => [0.7, 0, 50, 'The limit and the shortlist are at least 1; got 0 and 50.'];
        yield 'zero shortlist' => [0.7, 5, 0, 'The limit and the shortlist are at least 1; got 5 and 0.'];
    }

    #[DataProvider('invalidSettings')]
    public function testRefusesSettingsOutOfRange(float $minimum, int $limit, int $shortlist, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TranslationMemory(new InMemoryStore(), $minimum, $limit, $shortlist);
    }

    public function testAcceptsTheEdgesOfItsSettings(): void
    {
        self::assertSame([], new TranslationMemory(new InMemoryStore(), 1.0, 1, 1)->lookup('x', $this->en(), $this->fr()));
        self::assertSame([], new TranslationMemory(new InMemoryStore(), 0.01)->lookup('x', $this->en(), $this->fr()));
    }

    public function testAMatchShowsItsPercentRoundedDown(): void
    {
        $unit = new MemoryUnit($this->en(), $this->fr(), 'a', 'b');

        self::assertSame(57, new MemoryMatch($unit, 4 / 7, MatchKind::Fuzzy)->percent());
        self::assertSame(29, new MemoryMatch($unit, 0.29, MatchKind::Fuzzy)->percent());
        self::assertSame(99, new MemoryMatch($unit, 0.999, MatchKind::Fuzzy)->percent());
    }

    private function store(): InMemoryStore
    {
        return new InMemoryStore([
            new MemoryUnit($this->en(), $this->fr(), 'The quick brown fox', 'Le renard brun rapide'),
            new MemoryUnit($this->en(), $this->fr(), 'The quick brown dog', 'Le chien brun rapide'),
            new MemoryUnit($this->en(), $this->fr(), 'Something else entirely', 'Autre chose'),
            new MemoryUnit($this->en(), LanguageTag::of('es'), 'The quick brown fox', 'El zorro'),
        ]);
    }

    /**
     * @param list<MemoryMatch> $matches
     *
     * @return list<array{string, float, string}>
     */
    private function describe(array $matches): array
    {
        return array_map(static fn (MemoryMatch $match): array => [$match->kind->value, round($match->score, 2), $match->unit->target], $matches);
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
