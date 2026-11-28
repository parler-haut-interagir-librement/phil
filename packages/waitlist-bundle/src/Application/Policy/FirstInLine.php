<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Policy;

use Override;
use Phil\WaitlistBundle\Application\Contract\OfferPolicy;

/**
 * The waiting entries offered places strictly by rank: the first when the places held are enough for it, then the
 * next, and none before an earlier one that does not fit yet. A group of four never loses its turn to a single who
 * joined after it; the places wait, held, until the group fits.
 */
final readonly class FirstInLine implements OfferPolicy
{
    #[Override]
    public function pick(int $places, array $waiting): array
    {
        $picked = [];

        foreach ($waiting as $entry) {
            if ($entry->places() > $places) {
                break;
            }

            $picked[] = $entry;
            $places -= $entry->places();
        }

        return $picked;
    }
}
