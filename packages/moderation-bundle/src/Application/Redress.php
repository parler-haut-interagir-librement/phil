<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * The ways a member contests a decision, as a statement of reasons lists them: the terms of the site, the page where
 * the site takes a complaint, and the out-of-court dispute settlement bodies it accepts; going to court is always open
 * and the statement says so.
 */
final readonly class Redress
{
    /**
     * @param list<array{name: string, url: string}> $outOfCourt
     */
    public function __construct(
        public ?string $termsUrl = null,
        public ?string $complaintUrl = null,
        public array $outOfCourt = [],
    ) {
    }
}
