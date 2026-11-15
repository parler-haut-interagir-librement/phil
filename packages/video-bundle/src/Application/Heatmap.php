<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

/**
 * How often each hundredth of a video was watched, by all the viewers who agreed to be counted, and how many viewings
 * were counted: no viewing can be told from another.
 */
final readonly class Heatmap
{
    /**
     * @param list<int> $counts a hundred counts, one per part
     */
    public function __construct(
        public int $viewings = 0,
        public array $counts = [],
    ) {
    }

    /**
     * The share of the viewings that watched each part, in percent, a hundred values.
     *
     * @return list<int>
     */
    public function percentages(): array
    {
        $percentages = [];

        for ($part = 0; $part < ViewingProgress::PARTS; ++$part) {
            $percentages[] = 0 === $this->viewings ? 0 : intdiv(100 * ($this->counts[$part] ?? 0), $this->viewings);
        }

        return $percentages;
    }
}
