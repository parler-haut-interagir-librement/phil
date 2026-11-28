<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

/**
 * One neighbour of an item: its identifier, its score — null when a person chose it —, and why it is proposed, the
 * strongest reason first.
 */
final readonly class Recommendation
{
    /**
     * @param non-empty-list<Reason> $reasons
     */
    public function __construct(
        public string $id,
        public ?float $score,
        public array $reasons,
    ) {
    }

    public function hasReason(Reason $reason): bool
    {
        return \in_array($reason, $this->reasons, true);
    }

    /**
     * @return array{id: string, score: float|null, reasons: list<string>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'score' => $this->score, 'reasons' => array_map(static fn (Reason $reason): string => $reason->value, $this->reasons)];
    }

    /**
     * @param array{id: string, score: float|int|null, reasons: non-empty-list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['score'], array_map(Reason::from(...), $data['reasons']));
    }
}
