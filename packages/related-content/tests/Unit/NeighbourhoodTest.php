<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use DateTimeImmutable;
use Phil\RelatedContent\Contract\ItemSource;
use Phil\RelatedContent\Contract\PairCounts;
use Phil\RelatedContent\Index\DateProximity;
use Phil\RelatedContent\Index\KeywordIndex;
use Phil\RelatedContent\Item;
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\NeighbourhoodContext;
use Phil\RelatedContent\Pairs\InMemoryPairCounts;
use Phil\RelatedContent\Pairs\PairCount;
use Phil\RelatedContent\Pairs\Signal;
use Phil\RelatedContent\Reason;
use Phil\RelatedContent\Recommendation;
use Phil\RelatedContent\Relation\RelationType;
use Phil\RelatedContent\Relation\TypedRelation;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Similarity;
use Phil\RelatedContent\Tests\Support\MemoryPins;
use Phil\RelatedContent\Tests\Support\MemoryRelations;
use Phil\RelatedContent\Tests\Support\MemorySimilar;
use Phil\RelatedContent\Tests\Support\MemorySource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Neighbourhood::class)]
#[CoversClass(NeighbourhoodContext::class)]
#[CoversClass(Similarity::class)]
final class NeighbourhoodTest extends TestCase
{
    private function topics(string $id, string ...$topics): Item
    {
        return new Item($id, ['topics' => array_values($topics)]);
    }

    private function rules(float $threshold = 100.0, int $limit = 10, int $maxCandidates = 500, bool $includeNewer = true, int $minimumPairCount = 3): RelevanceRules
    {
        return new RelevanceRules([new KeywordIndex('topics', 100.0)], $threshold, $limit, $maxCandidates, $includeNewer, minimumPairCount: $minimumPairCount);
    }

    /**
     * @param list<Recommendation> $recommendations
     *
     * @return array<string, array{float|null, list<string>}>
     */
    private function summary(array $recommendations): array
    {
        $summary = [];

        foreach ($recommendations as $recommendation) {
            $summary[$recommendation->id] = [$recommendation->score, array_map(static fn (Reason $reason): string => $reason->value, $recommendation->reasons)];
        }

        return $summary;
    }

    public function testCandidatesAboveTheThresholdComeHighestFirstThenByIdentifier(): void
    {
        $item = $this->topics('a', 'x', 'y', 'z');
        $source = new MemorySource($item, $this->topics('c', 'x'), $this->topics('b', 'x'), $this->topics('d', 'x', 'y', 'w'), $this->topics('e', 'w'));
        $clock = new MockClock('2026-11-21 10:00:00');

        $set = new Neighbourhood($source, $this->rules(), clock: $clock)->compute($item);

        self::assertSame(['d' => [200.0, ['indices']], 'b' => [100.0, ['indices']], 'c' => [100.0, ['indices']]], $this->summary($set->recommendations));
        self::assertSame('a', $set->itemId);
        self::assertEquals($clock->now(), $set->computedAt);
    }

    public function testNothingIsProposedUnderTheThreshold(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'));

        self::assertSame([], new Neighbourhood($source, $this->rules(100.1))->compute($item)->recommendations);
        self::assertSame(['b'], new Neighbourhood($source, $this->rules(100.0))->compute($item)->ids());
    }

    public function testACandidateWithNothingInCommonIsNeverProposed(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'y'));

        self::assertSame([], new Neighbourhood($source, $this->rules(0.0))->compute($item)->recommendations);
    }

    public function testTheScoresOfTheIndicesAddUp(): void
    {
        $item = new Item('a', ['topics' => ['x'], 'authors' => ['ada']], new DateTimeImmutable('2026-11-10'));
        $candidate = new Item('b', ['topics' => ['x'], 'authors' => ['ada']], new DateTimeImmutable('2026-11-15'));
        $rules = new RelevanceRules([new KeywordIndex('topics', 100.0), new KeywordIndex('authors', 30.0), new DateProximity(20.0, 10)], 0.0);

        self::assertSame(['b' => [140.0, ['indices']]], $this->summary(new Neighbourhood(new MemorySource($item, $candidate), $rules)->compute($item)->recommendations));
    }

    public function testACandidateInAnotherLanguageIsLeftOut(): void
    {
        $item = new Item('a', ['topics' => ['x']], locale: 'fr');
        $source = new MemorySource($item, new Item('b', ['topics' => ['x']], locale: 'en'), new Item('c', ['topics' => ['x']], locale: 'fr'), new Item('d', ['topics' => ['x']]));

        self::assertSame(['c', 'd'], new Neighbourhood($source, $this->rules())->compute($item)->ids());
    }

    public function testNewerCandidatesCanBeLeftOut(): void
    {
        $item = new Item('a', ['topics' => ['x']], new DateTimeImmutable('2026-11-10'));
        $source = new MemorySource(
            $item,
            new Item('older', ['topics' => ['x']], new DateTimeImmutable('2026-11-09')),
            new Item('same', ['topics' => ['x']], new DateTimeImmutable('2026-11-10')),
            new Item('newer', ['topics' => ['x']], new DateTimeImmutable('2026-11-10 00:00:01')),
            new Item('undated', ['topics' => ['x']]),
        );

        self::assertSame(['newer', 'older', 'same', 'undated'], new Neighbourhood($source, $this->rules())->compute($item)->ids());
        self::assertSame(['older', 'same', 'undated'], new Neighbourhood($source, $this->rules(includeNewer: false))->compute($item)->ids());

        $undated = $this->topics('u', 'x');
        self::assertSame(['a', 'newer', 'older', 'same', 'undated'], new Neighbourhood(new MemorySource($undated, ...iterator_to_array($source->candidates($item), false)), $this->rules(includeNewer: false))->compute($undated)->ids());
    }

    public function testTheCandidatesReadAreBounded(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'), $this->topics('c', 'x'), $this->topics('d', 'x'));

        // The item itself is the first candidate read.
        self::assertSame(['b', 'c'], new Neighbourhood($source, $this->rules(maxCandidates: 3))->compute($item)->ids());
        self::assertSame(['b', 'c', 'd'], new Neighbourhood($source, $this->rules(maxCandidates: 4))->compute($item)->ids());
    }

    public function testTheLimitCountsEveryNeighbour(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'), $this->topics('c', 'x'), $this->topics('p', 'y'));

        self::assertSame(['p', 'b'], new Neighbourhood($source, $this->rules(limit: 2), new MemoryPins(['a' => ['p']]))->compute($item)->ids());
    }

    public function testPinnedNeighboursComeFirstInTheirOrderOnce(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'), $this->topics('p1', 'y'), $this->topics('p2', 'x'), new Item('p3', locale: 'en'));
        $item = new Item('a', ['topics' => ['x']], locale: 'fr');
        $source->put($item);
        $pins = new MemoryPins(['a' => ['p2', 'gone', 'a', 'p1', 'p2', 'p3']]);

        $set = new Neighbourhood($source, $this->rules(), $pins)->compute($item);

        self::assertSame(['p2' => [null, ['pinned']], 'p1' => [null, ['pinned']], 'b' => [100.0, ['indices']]], $this->summary($set->recommendations));
    }

    public function testTypedRelationsComeAfterThePinsAndComplementsHoldBothWays(): void
    {
        $item = $this->topics('game', 'x');
        $source = new MemorySource($item, $this->topics('sleeves'), $this->topics('deluxe'), $this->topics('dice'), $this->topics('map'), $this->topics('mat'), $this->topics('other'), $this->topics('pinned'));
        $relations = new MemoryRelations(
            new TypedRelation('game', 'sleeves', RelationType::Accessory),
            new TypedRelation('game', 'deluxe', RelationType::Upgrade),
            new TypedRelation('dice', 'game', RelationType::Complement),
            new TypedRelation('map', 'game', RelationType::Accessory),
            new TypedRelation('mat', 'game', RelationType::Upgrade),
            new TypedRelation('other', 'mat', RelationType::Complement),
            new TypedRelation('game', 'pinned', RelationType::Accessory),
        );

        $set = new Neighbourhood($source, $this->rules(), new MemoryPins(['game' => ['pinned']]), $relations)->compute($item);

        self::assertSame([
            'pinned' => [null, ['pinned']],
            'sleeves' => [null, ['accessory']],
            'deluxe' => [null, ['upgrade']],
            'dice' => [null, ['complement']],
        ], $this->summary($set->recommendations));
    }

    public function testAnUnpublishedRelationIsLeftOut(): void
    {
        $item = $this->topics('game');
        $relations = new MemoryRelations(new TypedRelation('game', 'gone', RelationType::Accessory), new TypedRelation('gone', 'game', RelationType::Complement));

        self::assertSame([], new Neighbourhood(new MemorySource($item), $this->rules(), relations: $relations)->compute($item)->recommendations);
    }

    public function testTheSearchEngineSimilarityAddsItsWeight(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'), $this->topics('c'), $this->topics('d'), new Item('e', locale: 'en'));
        $item = new Item('a', ['topics' => ['x']], locale: 'fr');
        $source->put($item);
        $similar = new MemorySimilar(new Similarity('b', 0.5), new Similarity('c', 2.0), new Similarity('d', 0.0), new Similarity('gone', 1.0), new Similarity('a', 1.0), new Similarity('e', 1.0), new Similarity('42', 0.9));
        $source->put($this->topics('42'));

        $set = new Neighbourhood($source, $this->rules(threshold: 90.0, limit: 7), similar: $similar)->compute($item);

        self::assertSame(['b' => [150.0, ['indices', 'similar']], 'c' => [100.0, ['similar']], '42' => [90.0, ['similar']]], $this->summary($set->recommendations));
        self::assertSame(7, $similar->askedLimit);
    }

    public function testPairCountsUnderTheMinimumAreIgnoredAndTheOthersScaleToTheHighest(): void
    {
        $item = $this->topics('game');
        $source = new MemorySource($item, $this->topics('dice'), $this->topics('sleeves'), $this->topics('rare'), $this->topics('next'));
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'game', 'gone', 20);
        $pairs->add(Signal::BoughtTogether, 'game', 'dice', 10);
        $pairs->add(Signal::BoughtTogether, 'game', 'sleeves', 5);
        $pairs->add(Signal::BoughtTogether, 'game', 'rare', 2);
        $pairs->add(Signal::ViewedNext, 'game', 'next', 4);
        $pairs->add(Signal::ViewedNext, 'game', 'dice', 4);

        $set = new Neighbourhood($source, $this->rules(threshold: 0.0), pairs: $pairs)->compute($item);

        self::assertSame([
            'dice' => [100.0, ['bought-together', 'viewed-next']],
            'next' => [50.0, ['viewed-next']],
            'sleeves' => [25.0, ['bought-together']],
        ], $this->summary($set->recommendations));
    }

    public function testTheHighestCountIsTheFirstAboveTheMinimum(): void
    {
        $item = $this->topics('game');
        $source = new MemorySource($item, $this->topics('dice'), $this->topics('sleeves'));
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'game', 'dice', 8);
        $pairs->add(Signal::BoughtTogether, 'game', 'sleeves', 4);

        self::assertSame(['dice' => [100.0, ['bought-together']], 'sleeves' => [50.0, ['bought-together']]], $this->summary(new Neighbourhood($source, $this->rules(0.0, minimumPairCount: 4), pairs: $pairs)->compute($item)->recommendations));
        self::assertSame(['dice' => [100.0, ['bought-together']]], $this->summary(new Neighbourhood($source, $this->rules(0.0, minimumPairCount: 5), pairs: $pairs)->compute($item)->recommendations));
    }

    public function testTheReasonsOfANeighbourComeStrongestFirst(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'));
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'a', 'b', 3);

        $rules = new RelevanceRules([new KeywordIndex('topics', 10.0)], 0.0, boughtTogetherWeight: 30.0);

        self::assertSame(['b' => [40.0, ['bought-together', 'indices']]], $this->summary(new Neighbourhood($source, $rules, pairs: $pairs)->compute($item)->recommendations));
    }

    public function testAManualNeighbourIsNotScoredAgain(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'x'));
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'a', 'b', 3);

        $set = new Neighbourhood($source, $this->rules(), new MemoryPins(['a' => ['b']]), similar: new MemorySimilar(new Similarity('b', 1.0)), pairs: $pairs)->compute($item);

        self::assertSame(['b' => [null, ['pinned']]], $this->summary($set->recommendations));
    }

    public function testAnItemReadOnceIsNotAskedAgain(): void
    {
        $item = $this->topics('a');
        $source = new MemorySource($item, $this->topics('b'));
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'a', 'b', 3);
        $pairs->add(Signal::ViewedNext, 'a', 'b', 3);

        new Neighbourhood($source, $this->rules(0.0), similar: new MemorySimilar(new Similarity('b', 1.0), new Similarity('gone', 1.0)), pairs: $pairs)->compute($item);

        self::assertSame(['b', 'gone'], $source->asked);
    }

    public function testASourceThatAnswersAnotherItemIsNotBelieved(): void
    {
        $item = $this->topics('a');
        $source = new readonly class($item) implements ItemSource {
            public function __construct(private Item $wrong)
            {
            }

            public function item(string $id): ?Item
            {
                return 'b' === $id ? $this->wrong : null;
            }

            public function candidates(Item $item): iterable
            {
                return [];
            }
        };

        self::assertSame([], new Neighbourhood($source, $this->rules(), new MemoryPins(['a' => ['b']]))->compute($item)->recommendations);
    }

    public function testTheRulesAreExposed(): void
    {
        $rules = $this->rules();

        self::assertSame($rules, new Neighbourhood(new MemorySource(), $rules)->rules);
        self::assertEquals(new RelevanceRules(), new Neighbourhood(new MemorySource())->rules);
    }

    public function testScoresAreRounded(): void
    {
        $item = $this->topics('a', 'x');
        $rules = new RelevanceRules([new KeywordIndex('topics', 1.0 / 3.0)], 0.0);

        self::assertSame(0.333333, new Neighbourhood(new MemorySource($item, $this->topics('b', 'x')), $rules)->compute($item)->recommendations[0]->score);
    }

    public function testTheCandidatesPastTheBoundAreNotEvenRead(): void
    {
        $item = $this->topics('a', 'x');
        $pulled = 0;
        $source = new class($pulled) implements ItemSource {
            public function __construct(private int &$pulled)
            {
            }

            public function item(string $id): ?Item
            {
                return null;
            }

            public function candidates(Item $item): iterable
            {
                foreach (range(1, 20) as $n) {
                    ++$this->pulled;

                    yield new Item('c'.$n, ['topics' => ['x']]);
                }
            }
        };

        $set = new Neighbourhood($source, $this->rules(maxCandidates: 3))->compute($item);

        self::assertCount(3, $set->recommendations);
        self::assertSame(4, $pulled);
    }

    public function testAPairUnderTheMinimumDoesNotStopTheOnesAfterIt(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'y'), $this->topics('c', 'z'));
        $pairs = new class implements PairCounts {
            public function add(Signal $signal, string $from, string $to, int $count = 1): void
            {
            }

            public function top(Signal $signal, string $id, int $limit): array
            {
                return Signal::BoughtTogether === $signal ? [new PairCount('b', 1), new PairCount('c', 5)] : [];
            }

            public function forget(string $id): void
            {
            }
        };

        $set = new Neighbourhood($source, $this->rules(), pairs: $pairs)->compute($item);

        self::assertSame(['c' => [100.0, ['bought-together']]], $this->summary($set->recommendations));
    }

    public function testTheSameReasonOfACandidateAddsUp(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'y'));
        $similar = new MemorySimilar(new Similarity('b', 0.5), new Similarity('b', 0.25));

        $set = new Neighbourhood($source, $this->rules(threshold: 50.0), similar: $similar)->compute($item);

        self::assertSame(['b' => [75.0, ['similar']]], $this->summary($set->recommendations));
    }

    public function testASimilarItemWithoutScoreOrTheItemItselfIsNotReadFromTheSource(): void
    {
        $item = $this->topics('a', 'x');
        $source = new MemorySource($item, $this->topics('b', 'y'));
        $similar = new MemorySimilar(new Similarity('b', 0.0), new Similarity('a', 1.0));

        $set = new Neighbourhood($source, $this->rules(threshold: 0.0), similar: $similar)->compute($item);

        self::assertSame([], $set->recommendations);
        self::assertSame([], $source->asked);
    }
}
