<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Memory;

use Override;
use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\Contract\ProofStore;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The proofs in an array, in the order they were added, for tests and for a single process. They are lost with the
 * object, or on `reset()`.
 */
final class InMemoryProofStore implements ProofStore, ResetInterface
{
    /** @var array<int, ConsentProof> */
    private array $proofs = [];

    #[Override]
    public function append(ConsentProof $proof): void
    {
        $this->proofs[] = $proof;
    }

    #[Override]
    public function ofMember(MemberId $member): array
    {
        return array_values(array_filter($this->proofs, static fn (ConsentProof $proof): bool => $proof->member->equals($member)));
    }

    #[Override]
    public function ofGuardian(GuardianId $guardian): array
    {
        return array_values(array_filter($this->proofs, static fn (ConsentProof $proof): bool => $proof->guardian?->equals($guardian) ?? false));
    }

    #[Override]
    public function removeMember(MemberId $member): int
    {
        $kept = array_filter($this->proofs, static fn (ConsentProof $proof): bool => !$proof->member->equals($member));
        $removed = \count($this->proofs) - \count($kept);
        $this->proofs = $kept;

        return $removed;
    }

    #[Override]
    public function forgetGuardian(GuardianId $guardian): int
    {
        $forgotten = 0;

        foreach ($this->proofs as $index => $proof) {
            if ($proof->guardian?->equals($guardian) ?? false) {
                $this->proofs[$index] = $proof->withoutGuardian();
                ++$forgotten;
            }
        }

        return $forgotten;
    }

    #[Override]
    public function reset(): void
    {
        $this->proofs = [];
    }
}
