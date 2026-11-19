<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;

/**
 * Where the proofs of consent are kept. Proofs are added, never changed, except to stop naming a forgotten guardian.
 */
interface ProofStore
{
    public function append(ConsentProof $proof): void;

    /**
     * The member's proofs, in the order they were added.
     *
     * @return list<ConsentProof>
     */
    public function ofMember(MemberId $member): array;

    /**
     * The proofs of the guardian's decisions, in the order they were added.
     *
     * @return list<ConsentProof>
     */
    public function ofGuardian(GuardianId $guardian): array;

    /**
     * Erases every proof about the member and returns how many there were.
     */
    public function removeMember(MemberId $member): int;

    /**
     * Stops naming the guardian in the proofs of their decisions and returns how many proofs named them.
     */
    public function forgetGuardian(GuardianId $guardian): int;
}
