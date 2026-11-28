<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Contract;

use Phil\WaitlistBundle\Domain\Model\Entry;

/**
 * Which waiting entries are offered the places a list holds. `Waitlists` asks it under the list's lock, offers the
 * places to the entries it picks, and refuses a pick that takes more places than are held or an entry that is not
 * among those it was given.
 */
interface OfferPolicy
{
    /**
     * @param int         $places  the places held, at least one
     * @param list<Entry> $waiting the waiting entries, by rank
     *
     * @return list<Entry> those to offer places to, in the order to offer them
     */
    public function pick(int $places, array $waiting): array;
}
