<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

use Countable;
use DateTimeImmutable;
use IteratorAggregate;
use Traversable;

/**
 * The neighbours of an item as they were computed and kept: the manual ones first, in the order a person set, then the
 * scored ones, highest first.
 *
 * @implements IteratorAggregate<int, Recommendation>
 */
final readonly class RelatedSet implements Countable, IteratorAggregate
{
    /**
     * @param list<Recommendation> $recommendations
     */
    public function __construct(
        public string $itemId,
        public array $recommendations,
        public DateTimeImmutable $computedAt,
    ) {
    }

    /**
     * The neighbours proposed for one reason, in their order: « Bought together », « Accessories ».
     *
     * @return list<Recommendation>
     */
    public function byReason(Reason $reason): array
    {
        return array_values(array_filter($this->recommendations, static fn (Recommendation $recommendation): bool => $recommendation->hasReason($reason)));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (Recommendation $recommendation): string => $recommendation->id, $this->recommendations);
    }

    public function contains(string $id): bool
    {
        return \in_array($id, $this->ids(), true);
    }

    /**
     * The first neighbours, at most `$limit`.
     *
     * @return list<Recommendation>
     */
    public function top(int $limit): array
    {
        return \array_slice($this->recommendations, 0, max(0, $limit));
    }

    public function count(): int
    {
        return \count($this->recommendations);
    }

    public function getIterator(): Traversable
    {
        yield from $this->recommendations;
    }

    /**
     * @return array{item: string, computedAt: string, recommendations: list<array{id: string, score: float|null, reasons: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'item' => $this->itemId,
            'computedAt' => $this->computedAt->format(\DATE_ATOM),
            'recommendations' => array_map(static fn (Recommendation $recommendation): array => $recommendation->toArray(), $this->recommendations),
        ];
    }

    /**
     * @param array{item: string, computedAt: string, recommendations: list<array{id: string, score: float|int|null, reasons: non-empty-list<string>}>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['item'], array_map(Recommendation::fromArray(...), $data['recommendations']), new DateTimeImmutable($data['computedAt']));
    }
}
