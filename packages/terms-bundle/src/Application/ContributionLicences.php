<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\ValueObject\Policy;

/**
 * Under which licence a member published a contribution: the licence of the highest version of the policy that names
 * one, among those the member had accepted at the moment of the contribution and that were in force at that moment.
 * The licence is chosen with the version and dated by the acceptance, so that a later version never changes the
 * licence of an earlier contribution.
 */
final readonly class ContributionLicences
{
    public function __construct(
        private Policies $policies,
        private VersionCatalog $catalog,
        private AcceptanceRepository $acceptances,
    ) {
    }

    /**
     * The licence of what the member contributed at a moment, or `null` when they had accepted no version naming one
     * by then, or when the application declares no such policy.
     */
    public function at(string $member, DateTimeImmutable $moment): ?ContributionLicence
    {
        $policy = $this->policies->contributionLicence();

        if (!$policy instanceof Policy) {
            return null;
        }

        $found = null;

        foreach ($this->acceptances->ofMember($member) as $acceptance) {
            if ($acceptance->policy !== $policy->key || $acceptance->acceptedAt > $moment || ($found instanceof ContributionLicence && $acceptance->version < $found->version)) {
                continue;
            }

            $version = $this->catalog->find($policy->key, $acceptance->version);

            if (null !== $version?->licence && $version->isInForceAt($moment)) {
                $found = new ContributionLicence($version->licence, $policy->key, $version->number, $acceptance->acceptedAt);
            }
        }

        return $found;
    }
}
