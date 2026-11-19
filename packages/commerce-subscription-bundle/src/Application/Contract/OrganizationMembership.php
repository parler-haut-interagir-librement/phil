<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application\Contract;

/**
 * Which organizations a member belongs to, as the application knows it: `FeatureAccess` reads it to open the
 * features of an organization's subscriptions to its members.
 *
 * The application implements it, over its own model of organizations; a service implementing it is the one
 * `FeatureAccess` receives. Without one, a subscriber's features are those of their own subscriptions only.
 */
interface OrganizationMembership
{
    /**
     * @return list<string> the stable identifiers of the organizations the member belongs to, as the organizations'
     *                      subscriptions name their subscriber; the first 100 are read
     */
    public function organizationsOf(string $member): array;
}
