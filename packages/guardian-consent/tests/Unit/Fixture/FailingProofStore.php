<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Fixture;

use Override;
use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\Contract\ProofStore;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Memory\InMemoryProofStore;
use RuntimeException;

/**
 * A store whose writes fail from the given one on, the way a database lost in the middle of an answer does.
 */
final class FailingProofStore implements ProofStore
{
    /** The number of the write that fails first, from 1; null when none fails. */
    public ?int $failFrom = null;

    private int $writes = 0;

    public function __construct(
        public readonly InMemoryProofStore $inner = new InMemoryProofStore(),
    ) {
    }

    #[Override]
    public function append(ConsentProof $proof): void
    {
        ++$this->writes;

        if (null !== $this->failFrom && $this->writes >= $this->failFrom) {
            throw new RuntimeException('The database is gone.');
        }

        $this->inner->append($proof);
    }

    #[Override]
    public function ofMember(MemberId $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function ofGuardian(GuardianId $guardian): array
    {
        return $this->inner->ofGuardian($guardian);
    }

    #[Override]
    public function removeMember(MemberId $member): int
    {
        return $this->inner->removeMember($member);
    }

    #[Override]
    public function forgetGuardian(GuardianId $guardian): int
    {
        return $this->inner->forgetGuardian($guardian);
    }
}
