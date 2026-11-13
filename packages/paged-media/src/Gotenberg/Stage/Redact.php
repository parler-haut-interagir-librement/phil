<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;

/**
 * Removes the text and the images under each region from the file itself, not only from view; nothing is drawn in
 * their place.
 */
final readonly class Redact implements Stage
{
    /** @var non-empty-list<Region> */
    public array $regions;

    public function __construct(Region ...$regions)
    {
        $regions = array_values($regions);

        if ([] === $regions) {
            throw InvalidStage::because('A redaction takes one region at least.');
        }

        $this->regions = $regions;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Redact;
    }

    #[Override]
    public function options(): array
    {
        return ['regions' => array_map(static fn (Region $region): array => $region->toArray(), $this->regions)];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
