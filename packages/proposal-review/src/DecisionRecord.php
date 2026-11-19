<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ProposalReview\Internal\Identifier;
use Phil\ProposalReview\Internal\Utc;

/**
 * A decision for a proposal, who recorded it and when; pending until the board applies the campaign's decisions
 * together, applied from then on. Instants are kept in UTC.
 */
final readonly class DecisionRecord
{
    public string $campaign;

    public string $proposal;

    public string $recordedBy;

    public DateTimeImmutable $recordedAt;

    public ?DateTimeImmutable $appliedAt;

    public function __construct(
        string $campaign,
        string $proposal,
        public Decision $decision,
        string $recordedBy,
        DateTimeInterface $recordedAt,
        ?DateTimeInterface $appliedAt = null,
    ) {
        $this->campaign = Identifier::check($campaign, 'campaign');
        $this->proposal = Identifier::check($proposal, 'proposal');
        $this->recordedBy = Identifier::check($recordedBy, 'member');
        $this->recordedAt = Utc::of($recordedAt);
        $this->appliedAt = $appliedAt instanceof DateTimeInterface ? Utc::of($appliedAt) : null;
    }

    public function isPending(): bool
    {
        return !$this->appliedAt instanceof DateTimeImmutable;
    }

    public function appliedAt(DateTimeInterface $instant): self
    {
        return new self($this->campaign, $this->proposal, $this->decision, $this->recordedBy, $this->recordedAt, $instant);
    }
}
