<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Repository;

use Phil\ShiftsBundle\Domain\Model\GuardianContact;

/**
 * The contacts of the guardians of volunteers who are minors, one per volunteer.
 */
interface GuardianRepository
{
    /**
     * Adds the contact, or replaces the one the volunteer has.
     */
    public function save(GuardianContact $contact): void;

    /**
     * @phpstan-impure
     */
    public function find(string $volunteer): ?GuardianContact;

    /**
     * Deletes the contact of the volunteer, if any.
     */
    public function remove(string $volunteer): void;
}
