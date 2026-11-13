<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

/**
 * What the cross-agreement rule decided of a note, with everything that explains it: the status, the reason, the
 * ratings counted for each verdict, the verdict the rule looked at, and the pairs of raters who usually disagree and
 * gave it together. Kept with the note, it says why the note is shown or not to whoever asks.
 */
final readonly class Decision
{
    /**
     * @param list<Bridge> $bridges
     */
    public function __construct(
        public NoteStatus $status,
        public DecisionReason $reason,
        public int $helpful,
        public int $notHelpful,
        public ?Verdict $verdict,
        public array $bridges,
        public CrossAgreementRule $rule,
    ) {
    }

    public function ratings(): int
    {
        return $this->helpful + $this->notHelpful;
    }

    /**
     * The decision as plain values, for a page, a log or an audit.
     *
     * @return array{status: string, reason: string, helpful: int, not_helpful: int, verdict: ?string, bridges: list<array{first: string, second: string, shared: int, agreed: int}>, rule: array{min_ratings: int, share: string, pairs: int, min_shared: int, divergence: string, max_raters: int}}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason->value,
            'helpful' => $this->helpful,
            'not_helpful' => $this->notHelpful,
            'verdict' => $this->verdict?->value,
            'bridges' => array_map(static fn (Bridge $bridge): array => $bridge->toArray(), $this->bridges),
            'rule' => $this->rule->toArray(),
        ];
    }

    /**
     * The decision as plain values without the raters' names, for the readers of a page: the evidence of each pair is
     * kept, the notes the two raters shared and those they agreed on.
     *
     * @return array{status: string, reason: string, helpful: int, not_helpful: int, verdict: ?string, bridges: list<array{shared: int, agreed: int}>, rule: array{min_ratings: int, share: string, pairs: int, min_shared: int, divergence: string, max_raters: int}}
     */
    public function toPublicArray(): array
    {
        return [
            'status' => $this->status->value,
            'reason' => $this->reason->value,
            'helpful' => $this->helpful,
            'not_helpful' => $this->notHelpful,
            'verdict' => $this->verdict?->value,
            'bridges' => array_map(static fn (Bridge $bridge): array => ['shared' => $bridge->history->shared, 'agreed' => $bridge->history->agreed], $this->bridges),
            'rule' => $this->rule->toArray(),
        ];
    }
}
