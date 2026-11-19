<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Fixtures;

use DateTimeImmutable;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\MapBundle\Domain\ValueObject\Booth;

/**
 * Booths in the states given, and the periods it was asked about.
 */
final class FixedAvailability implements BoothAvailability
{
    /** @var list<array{list<string>, string, string}> */
    public array $asked = [];

    /**
     * @param array<string, BoothState> $states
     */
    public function __construct(
        private readonly array $states = [],
    ) {
    }

    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $this->asked[] = [array_map(static fn (Booth $booth): string => $booth->id, $booths), $start->format(\DATE_ATOM), $end->format(\DATE_ATOM)];

        return $this->states;
    }
}
