<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Repository;

use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;

/**
 * Finds and stores customer groups and the buyers' memberships. `Infrastructure\Doctrine\DoctrineWholesaleRepository`
 * is the Doctrine adapter.
 */
interface CustomerGroupRepository
{
    public function findGroup(string $code): ?CustomerGroup;

    public function addGroup(CustomerGroup $group): void;

    public function membershipOf(string $buyer): ?Membership;

    public function addMembership(Membership $membership): void;

    public function removeMembership(Membership $membership): void;
}
