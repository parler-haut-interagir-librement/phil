<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use Phil\RelatedContent\Contract\RelatedStore;
use Phil\RelatedContent\Index\KeywordIndex;
use Phil\RelatedContent\Item;
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\Pairs\InMemoryPairCounts;
use Phil\RelatedContent\Pairs\Signal;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelatedSet;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;
use Phil\RelatedContent\Tests\Support\MemoryPins;
use Phil\RelatedContent\Tests\Support\MemorySource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelatedContent::class)]
final class RelatedContentTest extends TestCase
{
    private MemorySource $source;

    private InMemoryRelatedStore $store;

    protected function setUp(): void
    {
        $this->source = new MemorySource();
        $this->store = new InMemoryRelatedStore();
    }

    private function topics(string $id, string ...$topics): Item
    {
        return new Item($id, ['topics' => array_values($topics)]);
    }

    private function related(int $maxRecomputes = 100, ?InMemoryPairCounts $pairs = null, ?MemoryPins $pins = null): RelatedContent
    {
        $rules = new RelevanceRules([new KeywordIndex('topics', 100.0)], 100.0, minimumPairCount: 1, maxRecomputes: $maxRecomputes);

        return new RelatedContent($this->source, new Neighbourhood($this->source, $rules, $pins, pairs: $pairs), $this->store, $pairs);
    }

    /**
     * @return list<string>|null
     */
    private function ids(string $id): ?array
    {
        return $this->store->find($id)?->ids();
    }

    public function testAPageReadsTheKeptNeighboursWithoutComputing(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('b', 'x'));

        $related = $this->related();

        self::assertNull($related->related('a'));
        self::assertSame([], $this->source->asked);

        $set = $related->compute('a');

        self::assertSame(['b'], $set?->ids());
        self::assertSame($set, $this->store->find('a'));
    }

    public function testAnItemNoLongerPublishedLosesItsNeighbours(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('b', 'x'));

        $related = $this->related();
        $related->compute('a');

        $this->source->retire('a');

        self::assertNull($related->compute('a'));
        self::assertNull($related->related('a'));
    }

    public function testAPublicationComputesTheItemsThatShowedItAndItsNewNeighbours(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('b', 'x'));
        $this->source->put($this->topics('c', 'y'));

        $related = $this->related();
        $related->published('a');
        $related->published('c');

        self::assertSame(['a'], $this->ids('b'));

        // a moves from x to y: b, which showed it, loses it; c, its new neighbour, gains it.
        $this->source->put($this->topics('a', 'y'));
        $recomputation = $related->published('a');

        self::assertSame(['b', 'c'], $recomputation->recomputed);
        self::assertSame([], $recomputation->deferred);
        self::assertSame([], $this->ids('b'));
        self::assertSame(['a'], $this->ids('c'));
        self::assertSame(['c'], $this->ids('a'));
    }

    public function testRecomputationsPastTheBoundAreDeferred(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $id) {
            $this->source->put($this->topics($id, 'x'));
        }

        $recomputation = $this->related(2)->published('a');

        self::assertSame(['b', 'c'], $recomputation->recomputed);
        self::assertSame(['d'], $recomputation->deferred);
        self::assertNotNull($this->store->find('c'));
        self::assertNull($this->store->find('d'));
    }

    public function testAnItemIsComputedOnceEvenWhenItShowedAndIsShown(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('1', 'x'));

        $related = $this->related();
        $related->published('1');

        self::assertSame(['1'], $related->published('a')->recomputed);
    }

    public function testPublishingAnItemThatIsNotPublishedRetiresIt(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('b', 'x'));

        $related = $this->related();
        $related->published('a');

        $this->source->retire('a');

        $recomputation = $related->published('a');

        self::assertSame(['b'], $recomputation->recomputed);
        self::assertNull($related->related('a'));
        self::assertSame([], $this->ids('b'));
    }

    public function testARetiredItemLeavesEveryListAndItsPairsAreForgotten(): void
    {
        $pairs = new InMemoryPairCounts();
        $pairs->add(Signal::BoughtTogether, 'a', 'b', 5);
        $pairs->add(Signal::BoughtTogether, 'c', 'b', 5);

        $this->source->put($this->topics('a'));
        $this->source->put($this->topics('b'));
        $this->source->put($this->topics('c'));

        $related = $this->related(pairs: $pairs, pins: new MemoryPins(['c' => ['a']]));
        $related->published('a');
        $related->published('c');

        self::assertSame(['a', 'b'], $this->ids('c'));

        $this->source->retire('a');
        $recomputation = $related->retired('a');

        self::assertSame(['c', 'b'], $recomputation->recomputed);
        self::assertNull($related->related('a'));
        self::assertSame(['b'], $this->ids('c'));
        self::assertSame([], $pairs->top(Signal::BoughtTogether, 'a', 10));
        self::assertSame(['c'], $this->ids('b'));
    }

    public function testRetiringWithoutPairCountsWorks(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $related = $this->related();
        $related->published('a');

        self::assertSame([], $related->retired('a')->recomputed);
        self::assertNull($related->related('a'));
    }

    public function testTheChangedItemIsNeverComputedAgainEvenWhenTheStoreStillPointsAtIt(): void
    {
        $this->source->put($this->topics('a', 'x'));
        $this->source->put($this->topics('b', 'x'));

        $store = new class implements RelatedStore {
            public function save(RelatedSet $set): void
            {
            }

            public function find(string $id): ?RelatedSet
            {
                return null;
            }

            public function remove(string $id): void
            {
            }

            public function pointingAt(string $id, int $limit): array
            {
                return ['a', 'b'];
            }
        };
        $rules = new RelevanceRules([new KeywordIndex('topics', 100.0)], 100.0);

        $recomputation = new RelatedContent($this->source, new Neighbourhood($this->source, $rules), $store)->retired('a');

        self::assertSame(['b'], $recomputation->recomputed);
        self::assertSame([], $recomputation->deferred);
    }
}
