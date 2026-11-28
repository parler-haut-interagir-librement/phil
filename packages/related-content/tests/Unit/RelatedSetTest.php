<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use DateTimeImmutable;
use Phil\RelatedContent\Reason;
use Phil\RelatedContent\Recommendation;
use Phil\RelatedContent\Recomputation;
use Phil\RelatedContent\RelatedSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelatedSet::class)]
#[CoversClass(Recommendation::class)]
#[CoversClass(Recomputation::class)]
final class RelatedSetTest extends TestCase
{
    private function set(): RelatedSet
    {
        return new RelatedSet('product:game', [
            new Recommendation('product:sleeves', null, [Reason::Accessory]),
            new Recommendation('product:dice', 150.5, [Reason::BoughtTogether, Reason::Indices]),
            new Recommendation('product:map', 90.0, [Reason::Indices]),
        ], new DateTimeImmutable('2026-11-21T10:00:00+00:00'));
    }

    public function testTheNeighboursByReasonKeepTheirOrder(): void
    {
        $set = $this->set();

        self::assertSame(['product:dice', 'product:map'], array_map(static fn (Recommendation $r): string => $r->id, $set->byReason(Reason::Indices)));
        self::assertSame('product:dice', $set->byReason(Reason::BoughtTogether)[0]->id);
        self::assertSame([], $set->byReason(Reason::Pinned));
    }

    public function testIdentifiersCountTopAndIteration(): void
    {
        $set = $this->set();

        self::assertSame(['product:sleeves', 'product:dice', 'product:map'], $set->ids());
        self::assertTrue($set->contains('product:map'));
        self::assertFalse($set->contains('product:other'));
        self::assertCount(3, $set);
        self::assertSame(['product:sleeves', 'product:dice'], array_map(static fn (Recommendation $r): string => $r->id, $set->top(2)));
        self::assertSame([], $set->top(0));
        self::assertSame([], $set->top(-1));
        self::assertSame($set->recommendations, iterator_to_array($set));
        self::assertSame('product:game', $set->itemId);
    }

    public function testARecommendationKnowsItsReasons(): void
    {
        $recommendation = new Recommendation('a', 1.0, [Reason::Indices, Reason::Similar]);

        self::assertTrue($recommendation->hasReason(Reason::Similar));
        self::assertFalse($recommendation->hasReason(Reason::Pinned));
    }

    public function testTheSetRoundTripsThroughAnArray(): void
    {
        $set = $this->set();
        $array = $set->toArray();

        self::assertSame([
            'item' => 'product:game',
            'computedAt' => '2026-11-21T10:00:00+00:00',
            'recommendations' => [
                ['id' => 'product:sleeves', 'score' => null, 'reasons' => ['accessory']],
                ['id' => 'product:dice', 'score' => 150.5, 'reasons' => ['bought-together', 'indices']],
                ['id' => 'product:map', 'score' => 90.0, 'reasons' => ['indices']],
            ],
        ], $array);
        self::assertEquals($set, RelatedSet::fromArray($array));
    }

    public function testAnIntegerScoreIsReadAsAFloat(): void
    {
        self::assertSame(90.0, Recommendation::fromArray(['id' => 'a', 'score' => 90, 'reasons' => ['indices']])->score);
    }

    public function testARecomputationKeepsBothLists(): void
    {
        $recomputation = new Recomputation(['a'], ['b']);

        self::assertSame(['a'], $recomputation->recomputed);
        self::assertSame(['b'], $recomputation->deferred);
    }
}
