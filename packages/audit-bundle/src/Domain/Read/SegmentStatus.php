<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\SiteSegment;

/**
 * A segment of the trail as a viewer shows it: its head row, and its breaks once it was verified.
 */
final readonly class SegmentStatus
{
    /**
     * @param list<ChainBreak>|null $breaks `null` when the segment was not verified
     */
    public function __construct(
        public string $segment,
        public ?AuditRow $head,
        public ?array $breaks = null,
    ) {
    }

    /**
     * The tenant whose site the segment belongs to, as its head names it; `null` for a segment shared by every site.
     */
    public function site(): ?string
    {
        return SiteSegment::isSite($this->segment) ? $this->head?->facts->tenant : null;
    }

    /**
     * The segment the application named, without the digest of its site.
     */
    public function base(): string
    {
        return SiteSegment::base($this->segment);
    }

    public function state(): ChainState
    {
        if (null === $this->breaks) {
            return ChainState::Unverified;
        }

        foreach ($this->breaks as $break) {
            if ($break->isAlarm()) {
                return ChainState::Broken;
            }
        }

        return [] === $this->breaks ? ChainState::Holds : ChainState::HoldsWithExplainedBreaks;
    }

    /**
     * @return list<ChainBreak> the breaks that fail the verification
     */
    public function alarms(): array
    {
        return array_values(array_filter($this->breaks ?? [], static fn (ChainBreak $break): bool => $break->isAlarm()));
    }
}
