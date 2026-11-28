<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Benchmark;

use DateTimeImmutable;
use Phil\RelatedContent\Item;
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\Pairs\InMemoryPairCounts;
use Phil\RelatedContent\Pairs\Signal;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Tests\Support\MemorySource;

/**
 * The hot path of a publication: one article scored against the 500 candidates the rules read by default — topics with
 * broader terms, authors, series, dates —, with the pair counts of a busy product.
 */
final readonly class NeighbourhoodBench
{
    private Item $item;

    private Neighbourhood $neighbourhood;

    public function __construct()
    {
        $source = new MemorySource();
        $pairs = new InMemoryPairCounts();

        for ($i = 1; $i <= 500; ++$i) {
            $source->put(new Item(
                'article:'.$i,
                [
                    'topics' => ['topic-'.($i % 40), 'topic-'.($i % 17), 'topic-'.($i % 5)],
                    'authors' => ['author-'.($i % 12)],
                    'series' => 0 === $i % 25 ? ['series-'.($i % 3)] : [],
                ],
                new DateTimeImmutable('2026-01-01')->modify('+'.$i.' days'),
                'en',
                ['topics' => ['parent-'.($i % 8), 'root']],
            ));
            $pairs->add(Signal::BoughtTogether, 'article:250', 'article:'.$i, $i % 30 + 1);
        }

        $this->item = new Item('article:250', ['topics' => ['topic-10', 'topic-12', 'topic-0'], 'authors' => ['author-10'], 'series' => ['series-1']], new DateTimeImmutable('2026-09-07'), 'en', ['topics' => ['parent-2', 'root']]);
        $source->put($this->item);
        $this->neighbourhood = new Neighbourhood($source, RelevanceRules::forContent(), pairs: $pairs);
    }

    public function benchComputeTheNeighboursOfAnArticle(): int
    {
        return \count($this->neighbourhood->compute($this->item));
    }
}
