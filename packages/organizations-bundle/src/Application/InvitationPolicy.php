<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Invitation;

/**
 * The limits of invitations, from the configuration: how long one lasts when its creator gives no expiry, the longest
 * it may last, how many uses it allows when its creator says nothing, and the most it may allow.
 */
final readonly class InvitationPolicy
{
    /**
     * @param positive-int $defaultLifetime seconds
     * @param positive-int $maxLifetime     seconds, at least the default
     * @param positive-int $defaultUses
     * @param positive-int $maxUses         at least the default, at most `Invitation::MAX_USES`
     *
     * @throws InvalidOrganization when the limits contradict each other
     */
    public function __construct(
        public int $defaultLifetime = 604_800,
        public int $maxLifetime = 2_592_000,
        public int $defaultUses = 1,
        public int $maxUses = 100,
    ) {
        if ($defaultLifetime > $maxLifetime) {
            throw InvalidOrganization::because('The default lifetime of an invitation is at most its longest lifetime.');
        }

        if ($defaultUses > $maxUses || $maxUses > Invitation::MAX_USES) {
            throw InvalidOrganization::because(\sprintf('The default uses of an invitation are at most its most uses, themselves at most %d.', Invitation::MAX_USES));
        }
    }
}
