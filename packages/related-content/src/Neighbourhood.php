<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use DateTimeImmutable;
use Phil\RelatedContent\Contract\ItemSource;
use Phil\RelatedContent\Contract\PairCounts;
use Phil\RelatedContent\Contract\Pins;
use Phil\RelatedContent\Contract\SimilarItems;
use Phil\RelatedContent\Contract\TypedRelations;
use Phil\RelatedContent\Pairs\Signal;
use Phil\RelatedContent\Relation\RelationType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;

/**
 * Computes the neighbours of one item: the pinned ones, then the typed relations, then the candidates scored by the
 * indices, the search engine's similarity and the anonymous pair counts, above the threshold. A neighbour that is not
 * published, or in another language, is left out. It keeps nothing between two calls.
 */
final readonly class Neighbourhood
{
    private const array SIGNALS = [Signal::BoughtTogether, Signal::ViewedNext];

    private ClockInterface $clock;

    public function __construct(
        private ItemSource $source,
        public RelevanceRules $rules = new RelevanceRules(),
        private ?Pins $pins = null,
        private ?TypedRelations $relations = null,
        private ?SimilarItems $similar = null,
        private ?PairCounts $pairs = null,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? Clock::get();
    }

    public function compute(Item $item): RelatedSet
    {
        $context = new NeighbourhoodContext($this->source, $item);

        foreach ($this->pins?->pinned($item->id) ?? [] as $id) {
            $context->propose($id, Reason::Pinned);
        }

        $this->relate($context, $item);
        $this->score($context, $item);

        return new RelatedSet($item->id, \array_slice($context->recommendations(), 0, $this->rules->limit), $this->clock->now());
    }

    private function relate(NeighbourhoodContext $context, Item $item): void
    {
        if (!$this->relations instanceof TypedRelations) {
            return;
        }

        foreach ($this->relations->from($item->id) as $relation) {
            if ($relation->from === $item->id) {
                $context->propose($relation->to, $relation->type->reason());
            }
        }

        foreach ($this->relations->to($item->id) as $relation) {
            if (RelationType::Complement === $relation->type && $relation->to === $item->id) {
                $context->propose($relation->from, Reason::Complement);
            }
        }
    }

    private function score(NeighbourhoodContext $context, Item $item): void
    {
        $read = 0;

        foreach ($this->source->candidates($item) as $candidate) {
            if (++$read > $this->rules->maxCandidates) {
                break;
            }

            if (!$this->rules->includeNewer && $item->date instanceof DateTimeImmutable && null !== $candidate->date && $candidate->date->getTimestamp() > $item->date->getTimestamp()) {
                continue;
            }

            $score = 0.0;

            foreach ($this->rules->indices as $index) {
                $score += $index->score($item, $candidate);
            }

            $context->add($candidate, Reason::Indices, $score);
        }

        foreach ($this->similar?->similar($item, $this->rules->limit) ?? [] as $similarity) {
            $context->addById($similarity->id, Reason::Similar, $this->rules->similarWeight * min(1.0, $similarity->score));
        }

        foreach (self::SIGNALS as $signal) {
            $this->pairs($context, $item, $signal);
        }

        $context->keepAbove($this->rules->threshold);
    }

    private function pairs(NeighbourhoodContext $context, Item $item, Signal $signal): void
    {
        if (!$this->pairs instanceof PairCounts) {
            return;
        }

        $weight = Signal::BoughtTogether === $signal ? $this->rules->boughtTogetherWeight : $this->rules->viewedNextWeight;
        $highest = null;

        foreach ($this->pairs->top($signal, $item->id, $this->rules->limit) as $pair) {
            if ($pair->count < $this->rules->minimumPairCount) {
                continue;
            }

            $highest ??= $pair->count;
            $context->addById($pair->other, $signal->reason(), $weight * $pair->count / $highest);
        }
    }
}
