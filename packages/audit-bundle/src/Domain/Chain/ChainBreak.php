<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Chain;

/**
 * Something the verification of a segment found wrong: a row changed, a link cut, a row missing, an anchor no row
 * matches. An unexplained break is an alarm; an explained one — an anchored head a covered discontinuity accounts
 * for — is still said, for a person to read, but does not fail the verification.
 */
final readonly class ChainBreak
{
    public function __construct(
        public string $segment,
        public ?int $sequence,
        public string $reason,
        public bool $explained = false,
    ) {
    }

    public function isAlarm(): bool
    {
        return !$this->explained;
    }

    public function toString(): string
    {
        return null === $this->sequence ? \sprintf('%s: %s', $this->segment, $this->reason) : \sprintf('%s #%d: %s', $this->segment, $this->sequence, $this->reason);
    }
}
