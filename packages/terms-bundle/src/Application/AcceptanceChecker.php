<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * What a member has still to accept at a moment: for each required policy, the version in force when the member has
 * not accepted its required version or a later one — or accepted it longer ago than the renewal of the policy allows.
 */
final readonly class AcceptanceChecker
{
    public function __construct(
        private Policies $policies,
        private VersionCatalog $catalog,
        private AcceptanceRepository $acceptances,
    ) {
    }

    /**
     * @return list<PolicyVersion> in the order the policies are declared
     */
    public function pending(string $member, DateTimeImmutable $at): array
    {
        $pending = [];

        foreach ($this->policies->required() as $policy) {
            $required = $this->catalog->required($policy->key, $at);

            if (!$required instanceof PolicyVersion || [] !== $this->acceptances->compliant($policy->key, [$member], $required->number, $policy->acceptedSince($at))) {
                continue;
            }

            $pending[] = $this->catalog->inForce($policy->key, $at) ?? $required;
        }

        return $pending;
    }
}
