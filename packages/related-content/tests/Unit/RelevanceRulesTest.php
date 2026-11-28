<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit;

use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Index\DateProximity;
use Phil\RelatedContent\Index\KeywordIndex;
use Phil\RelatedContent\RelevanceRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelevanceRules::class)]
#[CoversClass(InvalidRule::class)]
final class RelevanceRulesTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $rules = new RelevanceRules();

        self::assertSame([], $rules->indices);
        self::assertSame(80.0, $rules->threshold);
        self::assertSame(10, $rules->limit);
        self::assertSame(500, $rules->maxCandidates);
        self::assertTrue($rules->includeNewer);
        self::assertSame(100.0, $rules->similarWeight);
        self::assertSame(100.0, $rules->boughtTogetherWeight);
        self::assertSame(50.0, $rules->viewedNextWeight);
        self::assertSame(3, $rules->minimumPairCount);
        self::assertSame(100, $rules->maxRecomputes);
    }

    public function testTheRulesForContent(): void
    {
        $rules = RelevanceRules::forContent();

        self::assertEquals([
            new KeywordIndex('topics', 100.0, 0.25),
            new KeywordIndex('authors', 40.0),
            new KeywordIndex('series', 200.0),
            new DateProximity(20.0, 365),
        ], $rules->indices);
        self::assertSame(80.0, $rules->threshold);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $low = new RelevanceRules([], 0.0, 1, 1, false, 0.0, 0.0, 0.0, 1, 1);
        $high = new RelevanceRules(array_fill(0, 32, new KeywordIndex('a', 1.0)), 1e9, 100, 10_000, true, 1.0, 1.0, 1.0, 1_000_000, 10_000);

        self::assertFalse($low->includeNewer);
        self::assertSame(1, $low->limit);
        self::assertSame(1, $low->maxCandidates);
        self::assertSame(1, $low->minimumPairCount);
        self::assertSame(1, $low->maxRecomputes);
        self::assertSame(0.0, $low->threshold);
        self::assertSame(0.0, $low->similarWeight);
        self::assertSame(0.0, $low->boughtTogetherWeight);
        self::assertSame(0.0, $low->viewedNextWeight);
        self::assertCount(32, $high->indices);
        self::assertSame(100, $high->limit);
        self::assertSame(10_000, $high->maxCandidates);
        self::assertSame(1_000_000, $high->minimumPairCount);
        self::assertSame(10_000, $high->maxRecomputes);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'too many indices' => [['indices' => array_fill(0, 33, new KeywordIndex('a', 1.0))], 'indices is 33; it must lie between 0 and 32.'];
        yield 'negative threshold' => [['threshold' => -1.0], 'threshold is -1; it must lie between 0 and '];
        yield 'infinite threshold' => [['threshold' => \INF], 'threshold is INF'];
        yield 'threshold not a number' => [['threshold' => \NAN], 'threshold is NAN'];
        yield 'no limit' => [['limit' => 0], 'limit is 0; it must lie between 1 and 100.'];
        yield 'limit too high' => [['limit' => 101], 'limit is 101; it must lie between 1 and 100.'];
        yield 'no candidate' => [['maxCandidates' => 0], 'maxCandidates is 0; it must lie between 1 and 10000.'];
        yield 'too many candidates' => [['maxCandidates' => 10_001], 'maxCandidates is 10001; it must lie between 1 and 10000.'];
        yield 'negative similar weight' => [['similarWeight' => -1.0], 'similarWeight is -1'];
        yield 'negative bought-together weight' => [['boughtTogetherWeight' => -1.0], 'boughtTogetherWeight is -1'];
        yield 'negative viewed-next weight' => [['viewedNextWeight' => -1.0], 'viewedNextWeight is -1'];
        yield 'no pair count' => [['minimumPairCount' => 0], 'minimumPairCount is 0; it must lie between 1 and 1000000.'];
        yield 'pair count too high' => [['minimumPairCount' => 1_000_001], 'minimumPairCount is 1000001'];
        yield 'no recompute' => [['maxRecomputes' => 0], 'maxRecomputes is 0; it must lie between 1 and 10000.'];
        yield 'too many recomputes' => [['maxRecomputes' => 10_001], 'maxRecomputes is 10001'];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalidRules')]
    public function testARuleOutOfBoundsIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        new RelevanceRules(...$arguments); // @phpstan-ignore argument.type (the provider names valid parameters)
    }
}
