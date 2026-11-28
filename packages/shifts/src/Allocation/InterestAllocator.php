<?php

declare(strict_types=1);

namespace Phil\Shifts\Allocation;

use Phil\Shifts\Roster\Place;

/**
 * Chooses, among the interested of a shift, who gets its free places. A roster keeps only the volunteers it returns
 * that are interested, once each, and no more than the places.
 */
interface InterestAllocator
{
    /**
     * @param non-empty-list<Place> $interested  in the order they expressed their interest
     * @param positive-int          $places      the free places
     * @param array<string, int>    $minutesHeld the minutes each volunteer already holds elsewhere
     *
     * @return list<string> the volunteers chosen
     */
    public function choose(array $interested, int $places, array $minutesHeld): array;
}
