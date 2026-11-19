<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

/**
 * A canned reply an agent may pick: its identifier and the label the page shows.
 */
final readonly class CannedReply
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
    }
}
