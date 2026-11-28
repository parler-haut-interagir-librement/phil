<?php

declare(strict_types=1);

namespace Phil\Shifts\Allocation;

use Override;

/**
 * The allocator that balances the load: the volunteers who hold the fewest minutes first, then those who expressed
 * their interest first, then by identifier, so that the same roster always allocates the same way.
 */
final readonly class FairAllocator implements InterestAllocator
{
    #[Override]
    public function choose(array $interested, int $places, array $minutesHeld): array
    {
        $order = [];

        foreach ($interested as $place) {
            $order[] = [$minutesHeld[$place->volunteer] ?? 0, $place->at->getTimestamp(), $place->volunteer];
        }

        usort($order, static fn (array $a, array $b): int => $a <=> $b);

        return array_map(static fn (array $entry): string => $entry[2], \array_slice($order, 0, $places));
    }
}
