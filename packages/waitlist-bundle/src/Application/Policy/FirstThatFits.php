<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Policy;

use Override;
use Phil\WaitlistBundle\Application\Contract\OfferPolicy;

/**
 * The waiting entries offered places by rank, passing over those that need more than is left: a freed place goes to
 * the first single behind a group of four, and the group keeps its rank for the next places. No place stays held while
 * an entry fits it.
 */
final readonly class FirstThatFits implements OfferPolicy
{
    #[Override]
    public function pick(int $places, array $waiting): array
    {
        $picked = [];

        foreach ($waiting as $entry) {
            if ($entry->places() <= $places) {
                $picked[] = $entry;
                $places -= $entry->places();
            }
        }

        return $picked;
    }
}
