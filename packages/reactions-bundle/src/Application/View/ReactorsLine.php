<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\View;

/**
 * Who chose a reaction: the first names, and how many others there are when the viewer sees the counters.
 */
final readonly class ReactorsLine
{
    /**
     * @param list<string> $names
     */
    public function __construct(
        public string $label,
        public array $names,
        public int $others,
    ) {
    }
}
